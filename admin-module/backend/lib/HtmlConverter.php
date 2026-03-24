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
        $h3Title = $block['h3_title'];
        $content = $block['content'];
        $normKey = MarkdownParser::normalizeTitle($h3Title);

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
        $classStr = implode(' ', $classes);

        // Construir HTML interno
        $inner = '';

        if ($cfg['show_h3']) {
            $inner .= '<h3>' . htmlspecialchars($h3Title, ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
        }

        $inner .= $this->markdownToHtml($content);

        return '<div class="' . $classStr . '">' . "\n" . $inner . '</div>' . "\n\n";
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
        // Desescapar puntos exportados por Google Docs: "1\. texto" → "1. texto"
        $text = str_replace('\\.', '.', $text);

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
