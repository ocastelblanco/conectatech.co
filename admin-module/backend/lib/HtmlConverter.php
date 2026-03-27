<?php
/**
 * HtmlConverter.php
 * Convierte bloques semánticos del árbol del parser a HTML,
 * usando el mapa definido en config/semantic-blocks.json.
 */

class HtmlConverter
{
    private array $config;   // contenido de semantic-blocks.json
    private array $blockMap; // h3_title normalizado → config del bloque

    public function __construct(string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new RuntimeException("No se encontró semantic-blocks.json en: {$configPath}");
        }

        $this->config = json_decode(file_get_contents($configPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al parsear semantic-blocks.json: " . json_last_error_msg());
        }

        // Construir mapa con claves normalizadas
        $this->blockMap = [];
        foreach ($this->config['blocks'] as $block) {
            $key = MarkdownParser::normalizeTitle($block['h3_title']);
            $this->blockMap[$key] = $block;
        }
    }

    // -------------------------------------------------------------------------
    // Conversión de una subsección completa a HTML
    // -------------------------------------------------------------------------

    /**
     * Convierte todos los bloques de una subsección (recurso-raiz o subseccion-regular)
     * a un único string HTML listo para insertar en un mod_label de Moodle.
     *
     * @param array $blocks  Array de ['h3_title' => ..., 'content' => ...]
     * @return string        HTML completo de la subsección
     */
    public function convertBlocks(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $html .= $this->convertBlock($block);
        }

        return $html;
    }

    /**
     * Convierte un único bloque semántico a HTML.
     */
    public function convertBlock(array $block): string
    {
        $h3Title   = $block['h3_title'];
        $content   = $block['content'];
        $normKey   = MarkdownParser::normalizeTitle($h3Title);
        $titleInfo = self::parseBlockTitle($h3Title);

        // Buscar en el mapa; si no existe, usar fallback
        if (isset($this->blockMap[$normKey])) {
            $cfg = $this->blockMap[$normKey];
        } else {
            $cfg = $this->config['fallback'];
            error_log("INFO: H3 sin mapeo: '{$h3Title}' → usando bloque genérico");
        }

        // Construir clases CSS
        $classes = [$cfg['css_primary']];
        foreach ($cfg['css_additional'] as $cls) {
            $classes[] = $cls;
        }
        // Regla 2: título entre paréntesis → agregar slug como clase extra
        if ($titleInfo['css_slug'] !== null) {
            $classes[] = $titleInfo['css_slug'];
        }
        $classStr = implode(' ', $classes);

        // Construir HTML interno
        $inner = '';

        // Mostrar H3 solo si el config lo permite Y el título no es parentético
        if ($cfg['show_h3'] && $titleInfo['show_title']) {
            $inner .= '<h3>' . htmlspecialchars($h3Title, ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
        }

        $inner .= $this->markdownToHtml($content);

        return '<div class="' . $classStr . '">' . "\n" . $inner . '</div>' . "\n\n";
    }

    // -------------------------------------------------------------------------
    // Utilidades de título
    // -------------------------------------------------------------------------

    /**
     * Parsea el título de un bloque semántico.
     * Si el título está entre paréntesis (Contexto), retorna show_title=false,
     * el texto interior como label, y un slug CSS para usarlo como clase extra.
     *
     * @return array{show_title: bool, label: string, css_slug: string|null}
     */
    public static function parseBlockTitle(string $h3Title): array
    {
        if (preg_match('/^\((.+)\)$/', trim($h3Title), $m)) {
            $inner = trim($m[1]);
            return [
                'show_title' => false,
                'label'      => $inner,
                'css_slug'   => self::slugifyTitle($inner),
            ];
        }
        return ['show_title' => true, 'label' => $h3Title, 'css_slug' => null];
    }

    /**
     * Convierte un texto a slug kebab-case ASCII (para clases CSS).
     * Ej: "Puntos clave" → "puntos-clave"
     */
    public static function slugifyTitle(string $title): string
    {
        $slug = mb_strtolower(trim($title), 'UTF-8');
        $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û','ä','ë','ï','ö'];
        $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u','a','e','i','o'];
        $slug = str_replace($from, $to, $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    // -------------------------------------------------------------------------
    // Conversión básica de Markdown a HTML
    // -------------------------------------------------------------------------

    /**
     * Convierte el subconjunto de Markdown usado en el contenido de los bloques
     * a HTML. Maneja: párrafos, negrita, cursiva, listas, citas de bloque.
     */
    public function markdownToHtml(string $markdown): string
    {
        $lines  = explode("\n", trim($markdown));
        $html   = '';
        $inList = false;
        $inBlockquote = false;

        foreach ($lines as $line) {
            // Lista
            if (preg_match('/^[-*] (.+)$/', $line, $m)) {
                if (!$inList) {
                    $html  .= "<ul>\n";
                    $inList = true;
                }
                $html .= '<li>' . $this->inlineMarkdown(trim($m[1])) . "</li>\n";
                continue;
            }

            // Cerrar lista si ya no estamos en ella
            if ($inList && trim($line) !== '') {
                $html  .= "</ul>\n";
                $inList = false;
            }

            // Cita de bloque (> texto)
            if (preg_match('/^> (.+)$/', $line, $m)) {
                if (!$inBlockquote) {
                    $html .= "<blockquote>\n";
                    $inBlockquote = true;
                }
                $html .= '<p>' . $this->inlineMarkdown($m[1]) . "</p>\n";
                continue;
            }

            if ($inBlockquote && trim($line) !== '') {
                // Continuar en blockquote si hay más contenido (no vacío)
            } elseif ($inBlockquote) {
                $html .= "</blockquote>\n";
                $inBlockquote = false;
            }

            // Imagen de bloque: línea completa que es sólo una imagen
            // Soporta: ![alt](url)  y  ![alt](url){clase}
            if (preg_match('/^!\[([^\]]*)\]\(([^)]+)\)(?:\{([^}]+)\})?$/', trim($line), $im)) {
                if ($inList)       { $html .= "</ul>\n";         $inList       = false; }
                if ($inBlockquote) { $html .= "</blockquote>\n"; $inBlockquote = false; }
                $alt = htmlspecialchars($im[1], ENT_QUOTES, 'UTF-8');
                $src = htmlspecialchars($im[2], ENT_QUOTES, 'UTF-8');
                $cls = !empty($im[3]) ? ' class="' . htmlspecialchars($im[3], ENT_QUOTES, 'UTF-8') . '"' : '';
                $html .= '<figure' . $cls . '><img src="' . $src . '" alt="' . $alt . '"></figure>' . "\n";
                continue;
            }

            // Encabezado H4 (#### título)
            if (preg_match('/^#### (.+)$/', $line, $m)) {
                if ($inList)       { $html .= "</ul>\n";         $inList       = false; }
                if ($inBlockquote) { $html .= "</blockquote>\n"; $inBlockquote = false; }
                $html .= '<h4>' . $this->inlineMarkdown($m[1]) . "</h4>\n";
                continue;
            }

            // Párrafo vacío
            if (trim($line) === '') {
                if ($inList) {
                    $html  .= "</ul>\n";
                    $inList = false;
                }
                if ($inBlockquote) {
                    $html .= "</blockquote>\n";
                    $inBlockquote = false;
                }
                continue;
            }

            // Párrafo normal
            if (!$inList && !$inBlockquote) {
                $html .= '<p>' . $this->inlineMarkdown($line) . "</p>\n";
            }
        }

        // Cerrar elementos abiertos
        if ($inList) {
            $html .= "</ul>\n";
        }
        if ($inBlockquote) {
            $html .= "</blockquote>\n";
        }

        return $html;
    }

    /**
     * Convierte el markdown inline: **negrita**, _cursiva_, `código`.
     */
    private function inlineMarkdown(string $text): string
    {
        // Desescapar puntuación escapada por Google Docs (excluye * y _ para no romper bold/italic)
        $text = preg_replace('/\\\\([^\w\s*_])/', '$1', $text);

        // Imagen inline: ![alt](url) o ![alt](url){clase}
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)]+)\)(?:\{([^}]+)\})?/',
            static function (array $m): string {
                $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                $src = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
                $cls = !empty($m[3]) ? ' class="' . htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8') . '"' : '';
                return '<img src="' . $src . '" alt="' . $alt . '"' . $cls . '>';
            },
            $text
        );

        // Negrita: **texto** o __texto__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

        // Cursiva: _texto_ o *texto* (no seguido de segundo * o _)
        $text = preg_replace('/\*(?!\*)(.+?)(?<!\*)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(?!_)(.+?)(?<!_)_/', '<em>$1</em>', $text);

        // Código: `código`
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

        return $text;
    }

    // -------------------------------------------------------------------------
    // Accesores de configuración
    // -------------------------------------------------------------------------

    public function getPresaberesFeedbackMode(): string
    {
        return $this->config['presaberes_feedback_mode'] ?? 'data-attribute';
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
