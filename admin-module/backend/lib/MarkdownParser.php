<?php
/**
 * MarkdownParser.php
 * Tokeniza archivos Markdown con estructura H1/H2/H3/H4/H5
 * y construye el árbol de secciones/subsecciones/bloques.
 *
 * Tipos de subsección:
 *   - referente-biblico-seccion : primer H2 con título "Referente bíblico"
 *                                  → label directo en la sección padre
 *   - subseccion-regular        : H2 sin marcador con al menos un H3
 *   - h2-texto-directo          : H2 sin marcador y sin ningún H3
 *                                  → label directo en la sección padre (Regla 1)
 *   - subseccion-evaluacion     : H2 con [evaluacion]
 *   - subseccion-presaberes     : H2 con [presaberes]
 *
 * Dentro de subseccion-regular, un H3 con [evaluacion] genera un
 * cuestionario (h3_evaluaciones[]) con preguntas en H4.
 */

class MarkdownParser
{
    // Resultado final
    private array $sections = [];

    // Estado de sección (H1)
    private ?array $curSection = null;
    private bool $rootFoundInSection = false;

    // Estado de subsección (H2)
    private ?array $curSub = null;

    // Estado para bloques semánticos (subseccion-regular / referente-biblico-seccion)
    private ?array $curBlock = null;

    // Estado para H3-level evaluaciones dentro de subseccion-regular
    private ?array $curH3Eval  = null;  // ['title' => ..., 'questions' => []]
    private ?array $curH3EvalQ = null;  // pregunta activa dentro del H3-eval

    // Estado para preguntas de [evaluacion] en H2
    private ?array $curQuestion = null;
    private ?array $curOption   = null;

    // Estado para bloques de [presaberes]
    private ?array $curPresaberesBlock = null;
    private string $presCtx            = 'none'; // none | contexto | pregunta | enunciado | options
    private ?array $curPregunta        = null;
    private ?array $curPreguntaOption  = null;

    // -------------------------------------------------------------------------
    // Entrada pública
    // -------------------------------------------------------------------------

    public function parse(string $filepath): array
    {
        if (!file_exists($filepath)) {
            throw new RuntimeException("Archivo no encontrado: {$filepath}");
        }

        $content = file_get_contents($filepath);
        $content = $this->normalizeContent($content);
        $lines   = explode("\n", $content);

        foreach ($lines as $line) {
            $this->processLine($line);
        }

        $this->finalizeAll();

        return $this->sections;
    }

    // -------------------------------------------------------------------------
    // Procesamiento línea a línea
    // -------------------------------------------------------------------------

    private function processLine(string $line): void
    {
        // Ignorar separadores horizontales
        if (trim($line) === '---' || trim($line) === '***' || trim($line) === '___') {
            return;
        }

        if (preg_match('/^# (.+)$/', $line, $m)) {
            $this->handleH1(trim($m[1]));
        } elseif (preg_match('/^## (.+)$/', $line, $m)) {
            $this->handleH2(trim($m[1]));
        } elseif (preg_match('/^### (.+)$/', $line, $m)) {
            $this->handleH3(trim($m[1]));
        } elseif (preg_match('/^#### (.+)$/', $line, $m)) {
            $this->handleH4(trim($m[1]));
        } elseif (preg_match('/^##### (.+)$/', $line, $m)) {
            $this->handleH5(trim($m[1]));
        } else {
            $this->handleContent($line);
        }
    }

    // -------------------------------------------------------------------------
    // Manejadores de cabeceras
    // -------------------------------------------------------------------------

    private function handleH1(string $title): void
    {
        $this->finalizeSection();

        $title = self::stripTitleFormatting($title);
        $this->curSection         = ['title' => $title, 'subsections' => []];
        $this->rootFoundInSection = false;
    }

    private function handleH2(string $rawTitle): void
    {
        if ($this->curSection === null) {
            return;
        }

        $this->finalizeSubsection();

        // Limpiar bold, italic, corchetes y prefijo numérico ("1\. " o "1. ")
        $rawTitle = self::stripTitleFormatting($rawTitle);

        // Extraer marcador [evaluacion] | [presaberes] | [otro]
        $marker = null;
        $title  = $rawTitle;

        if (preg_match('/^(.*?)\s*\[([^\]]+)\]\s*$/', $rawTitle, $m)) {
            $title  = trim($m[1]);
            $marker = strtolower(trim($m[2]));
        }

        if ($marker === 'evaluacion') {
            $type = 'subseccion-evaluacion';
        } elseif ($marker === 'presaberes') {
            $type = 'subseccion-presaberes';
        } elseif ($marker !== null) {
            error_log("WARN: Marcador desconocido [{$marker}] en H2 '{$rawTitle}'");
            $type = 'subseccion-regular';
        } elseif (!$this->rootFoundInSection && self::normalizeTitle($title) === 'referente biblico') {
            // Primer H2 con título "Referente bíblico" → label directo en la sección
            $type = 'referente-biblico-seccion';
            $this->rootFoundInSection = true;
        } else {
            $type = 'subseccion-regular';
            $this->rootFoundInSection = true;
        }

        $this->curSub = [
            'type'            => $type,
            'title'           => $title,
            'blocks'          => [],
            'h3_evaluaciones' => [],   // cuestionarios H3-level (nueva estructura)
            'questions'       => [],   // preguntas H2-level [evaluacion]
            'pregunta_blocks' => [],   // bloques presaberes
        ];

        // Para referente-biblico-seccion: auto-iniciar bloque (no hay H3 en el fuente)
        if ($type === 'referente-biblico-seccion') {
            $this->curBlock = ['h3_title' => 'Referente bíblico', 'content' => ''];
        }

        // Para subseccion-regular: auto-iniciar bloque con el título H2.
        // Si no aparece ningún H3, se promoverá a 'h2-texto-directo' al finalizar.
        if ($type === 'subseccion-regular') {
            $this->curBlock = ['h3_title' => $title, 'content' => ''];
            $this->curSub['has_h3'] = false;
        }
    }

    private function handleH3(string $rawTitle): void
    {
        if ($this->curSub === null) {
            return;
        }

        $this->finalizeCurrentBlock();

        $rawTitle = self::stripTitleFormatting($rawTitle);

        // Detectar marcador [evaluacion] en H3
        $marker = null;
        $title  = $rawTitle;

        if (preg_match('/^(.*?)\s*\[([^\]]+)\]\s*$/', $rawTitle, $m)) {
            $title  = trim($m[1]);
            $marker = strtolower(trim($m[2]));
        }

        switch ($this->curSub['type']) {

            case 'referente-biblico-seccion':
                // Normalmente no tiene H3; ignorar
                break;

            case 'subseccion-regular':
                $this->curSub['has_h3'] = true;
                if ($marker === 'evaluacion') {
                    // H3 con [evaluacion] → cuestionario dentro de la subsección
                    $this->curH3Eval  = ['title' => $title, 'questions' => []];
                    $this->curH3EvalQ = null;
                } else {
                    // Bloque semántico normal (Referente bíblico, Reflexiona, etc.)
                    $this->curH3Eval  = null;
                    $this->curH3EvalQ = null;
                    $this->curBlock   = ['h3_title' => $title, 'content' => ''];
                }
                break;

            case 'subseccion-evaluacion':
                $this->curQuestion = [
                    'title'    => $title,
                    'tipo'     => 'ensayo',
                    'variante' => 'texto',
                    'enunciado'=> '',
                    'options'  => [],
                ];
                $this->curOption = null;
                break;

            case 'subseccion-presaberes':
                $this->curPresaberesBlock = [
                    'aria_label' => $title,
                    'context'    => null,
                    'pregunta'   => null,
                ];
                $this->presCtx           = 'none';
                $this->curPregunta       = null;
                $this->curPreguntaOption = null;
                break;
        }
    }

    private function handleH4(string $rawTitle): void
    {
        // H4 dentro de H3-level evaluacion (subseccion-regular)
        if (
            $this->curSub !== null
            && $this->curSub['type'] === 'subseccion-regular'
            && $this->curH3Eval !== null
        ) {
            $this->saveH3EvalQuestion();
            $title = self::stripTitleFormatting($rawTitle);
            $this->curH3EvalQ = [
                'title'    => $title,
                'tipo'     => 'ensayo',
                'variante' => 'texto',
                'enunciado'=> '',
                'options'  => [],
            ];
            $this->curOption = null;
            return;
        }

        // H4 en bloque de contenido genérico → agregar como encabezado al bloque
        if ($this->curBlock !== null) {
            $title = self::stripTitleFormatting($rawTitle);
            $this->curBlock['content'] .= "#### {$title}\n";
            return;
        }

        // Comportamiento original: H4 en subseccion-presaberes
        if ($this->curSub === null || $this->curSub['type'] !== 'subseccion-presaberes') {
            return;
        }
        if ($this->curPresaberesBlock === null) {
            return;
        }

        $this->savePreguntaOption();

        $norm = self::normalizeTitle($rawTitle);

        if ($norm === 'contexto') {
            $this->presCtx = 'contexto';
        } elseif ($norm === 'pregunta') {
            $this->presCtx     = 'pregunta';
            $this->curPregunta = [
                'tipo'     => 'opcion-multiple',
                'enunciado'=> '',
                'options'  => [],
            ];
            $this->curPreguntaOption = null;
        }
    }

    private function handleH5(string $title): void
    {
        if ($this->curSub === null || $this->curSub['type'] !== 'subseccion-presaberes') {
            return;
        }
        if (!in_array($this->presCtx, ['pregunta', 'enunciado'])) {
            return;
        }

        $this->savePreguntaOption();

        if ($this->curPregunta !== null) {
            $this->curPregunta['enunciado'] = $title;
            $this->presCtx = 'enunciado';
        }
    }

    // -------------------------------------------------------------------------
    // Manejador de contenido (no-cabecera)
    // -------------------------------------------------------------------------

    private function handleContent(string $line): void
    {
        if ($this->curSub === null) {
            return;
        }

        switch ($this->curSub['type']) {

            // -----------------------------------------------------------------
            case 'referente-biblico-seccion':
                if ($this->curBlock !== null) {
                    $this->curBlock['content'] .= $line . "\n";
                }
                break;

            // -----------------------------------------------------------------
            case 'subseccion-regular':
                if ($this->curH3Eval !== null) {
                    // Dentro de H3-level evaluacion
                    if ($this->curH3EvalQ === null) {
                        break;
                    }

                    // Metadatos {tipo: ...}
                    if (preg_match('/^\{tipo:\s*([^,}]+)(?:,\s*variante:\s*([^}]+))?\}/', trim($line), $m)) {
                        $this->curH3EvalQ['tipo'] = trim($m[1]);
                        if (!empty($m[2])) {
                            $this->curH3EvalQ['variante'] = trim($m[2]);
                        }
                        break;
                    }

                    // Sub-lista: feedback de la opción actual (sangría + "- texto" o "* texto")
                    if (preg_match('/^\s+[*-] (.+)$/', $line, $m) && $this->curOption !== null) {
                        $this->curOption['feedback'] = trim($m[1]);
                        break;
                    }

                    // Lista: opción de respuesta "- texto" o "* texto" (Google Docs usa *)
                    if (preg_match('/^[*-] (.+)$/', $line, $m)) {
                        if ($this->curOption !== null) {
                            $this->curH3EvalQ['options'][] = $this->curOption;
                        }
                        // Desescapar puntuación escapada por Google Docs (\[ \] \! \( \) etc.)
                        $optText = preg_replace('/\\\\([^\w\s])/', '$1', trim($m[1]));
                        $correct = false;
                        if (preg_match('/^(.+?)\s*\[correcta\]\s*$/', $optText, $om)) {
                            $optText = trim($om[1]);
                            $correct = true;
                        }
                        $this->curOption = ['text' => $optText, 'correct' => $correct, 'feedback' => ''];
                        break;
                    }

                    // Texto del enunciado (solo si no hay opciones aún)
                    if (trim($line) !== '' && empty($this->curH3EvalQ['options']) && $this->curOption === null) {
                        $this->curH3EvalQ['enunciado'] .= $line . "\n";
                    }
                } else {
                    // Bloque semántico regular
                    if ($this->curBlock !== null) {
                        $this->curBlock['content'] .= $line . "\n";
                    }
                }
                break;

            // -----------------------------------------------------------------
            case 'subseccion-evaluacion':
                if ($this->curQuestion === null) {
                    break;
                }

                // Metadatos {tipo: ...}
                if (preg_match('/^\{tipo:\s*([^,}]+)(?:,\s*variante:\s*([^}]+))?\}/', trim($line), $m)) {
                    $this->curQuestion['tipo'] = trim($m[1]);
                    if (!empty($m[2])) {
                        $this->curQuestion['variante'] = trim($m[2]);
                    }
                    break;
                }

                // Sub-lista (feedback)
                if (preg_match('/^\s+[*-] (.+)$/', $line, $m) && $this->curOption !== null) {
                    $this->curOption['feedback'] = trim($m[1]);
                    break;
                }

                // Lista: opción de respuesta ("- texto" o "* texto")
                if (preg_match('/^[*-] (.+)$/', $line, $m)) {
                    if ($this->curOption !== null) {
                        $this->curQuestion['options'][] = $this->curOption;
                    }
                    $optText = preg_replace('/\\\\([^\w\s])/', '$1', trim($m[1]));
                    $correct = false;
                    if (preg_match('/^(.+?)\s*\[correcta\]\s*$/', $optText, $om)) {
                        $optText = trim($om[1]);
                        $correct = true;
                    }
                    $this->curOption = ['text' => $optText, 'correct' => $correct, 'feedback' => ''];
                    break;
                }

                // Texto del enunciado
                if (trim($line) !== '') {
                    $this->curQuestion['enunciado'] .= $line . "\n";
                }
                break;

            // -----------------------------------------------------------------
            case 'subseccion-presaberes':
                if ($this->curPresaberesBlock === null) {
                    break;
                }

                // Metadatos {tipo: ...}
                if (preg_match('/^\{tipo:\s*([^}]+)\}/', trim($line), $m)) {
                    if ($this->curPregunta !== null) {
                        $this->curPregunta['tipo'] = trim($m[1]);
                    }
                    break;
                }

                // Sub-lista (feedback)
                if (preg_match('/^\s+- (.+)$/', $line, $m) && $this->curPreguntaOption !== null) {
                    $this->curPreguntaOption['feedback'] = trim($m[1]);
                    break;
                }

                // Lista: opción de respuesta
                if (preg_match('/^- (.+)$/', $line, $m) &&
                    in_array($this->presCtx, ['enunciado', 'options'])) {
                    $this->savePreguntaOption();
                    $optText = preg_replace('/\\\\([^\w\s])/', '$1', trim($m[1]));
                    $correct = false;
                    if (preg_match('/^(.+?)\s*\[correcta\]\s*$/', $optText, $om)) {
                        $optText = trim($om[1]);
                        $correct = true;
                    }
                    $this->curPreguntaOption = ['text' => $optText, 'correct' => $correct, 'feedback' => ''];
                    $this->presCtx = 'options';
                    break;
                }

                // Texto de contexto
                if ($this->presCtx === 'contexto' && trim($line) !== '') {
                    $this->curPresaberesBlock['context'] =
                        ($this->curPresaberesBlock['context'] ?? '') . $line . "\n";
                }
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Finalización
    // -------------------------------------------------------------------------

    private function finalizeCurrentBlock(): void
    {
        if ($this->curSub === null) {
            return;
        }

        // Guardar H3-eval activo si lo hay
        $this->saveH3Eval();

        switch ($this->curSub['type']) {
            case 'referente-biblico-seccion':
            case 'subseccion-regular':
                if ($this->curBlock !== null) {
                    $this->curBlock['content'] = rtrim($this->curBlock['content']);
                    // Si el bloque fue auto-inicializado en handleH2 (has_h3 = false) y está
                    // vacío (ningún contenido antes del primer H3), descartarlo silenciosamente.
                    $isEmpty     = $this->curBlock['content'] === '';
                    $isAutoBlock = !($this->curSub['has_h3'] ?? true);
                    if (!($isEmpty && $isAutoBlock)) {
                        $this->curSub['blocks'][] = $this->curBlock;
                    }
                    $this->curBlock = null;
                }
                break;

            case 'subseccion-evaluacion':
                $this->saveQuestion();
                break;

            case 'subseccion-presaberes':
                $this->savePresaberesBlock();
                break;
        }
    }

    // ─── H3-level evaluacion ───────────────────────────────────────────────

    private function saveH3EvalQuestion(): void
    {
        if ($this->curH3EvalQ !== null && $this->curH3Eval !== null) {
            // Guardar última opción pendiente (opcion-multiple)
            if ($this->curOption !== null) {
                $this->curH3EvalQ['options'][] = $this->curOption;
                $this->curOption               = null;
            }
            $this->curH3EvalQ['enunciado']  = rtrim($this->curH3EvalQ['enunciado']);
            $this->curH3Eval['questions'][] = $this->curH3EvalQ;
            $this->curH3EvalQ               = null;
        }
    }

    private function saveH3Eval(): void
    {
        $this->saveH3EvalQuestion();
        if ($this->curH3Eval !== null && $this->curSub !== null) {
            $this->curSub['h3_evaluaciones'][] = $this->curH3Eval;
            $this->curH3Eval                   = null;
            $this->curH3EvalQ                  = null;
        }
    }

    // ─── H2-level evaluacion ──────────────────────────────────────────────

    private function saveQuestion(): void
    {
        if ($this->curQuestion === null) {
            return;
        }
        if ($this->curOption !== null) {
            $this->curQuestion['options'][] = $this->curOption;
            $this->curOption = null;
        }
        $this->curQuestion['enunciado'] = rtrim($this->curQuestion['enunciado']);
        $this->curSub['questions'][]    = $this->curQuestion;
        $this->curQuestion              = null;
    }

    // ─── Presaberes ───────────────────────────────────────────────────────

    private function savePreguntaOption(): void
    {
        if ($this->curPreguntaOption !== null && $this->curPregunta !== null) {
            $this->curPregunta['options'][] = $this->curPreguntaOption;
            $this->curPreguntaOption        = null;
        }
    }

    private function savePresaberesBlock(): void
    {
        if ($this->curPresaberesBlock === null) {
            return;
        }

        $this->savePreguntaOption();

        if ($this->curPregunta !== null) {
            $this->curPresaberesBlock['pregunta'] = $this->curPregunta;
            $this->curPregunta = null;
        }

        if ($this->curPresaberesBlock['context'] !== null) {
            $this->curPresaberesBlock['context'] = rtrim($this->curPresaberesBlock['context']);
        }

        $this->curSub['pregunta_blocks'][] = $this->curPresaberesBlock;
        $this->curPresaberesBlock          = null;
        $this->presCtx                     = 'none';
    }

    // ─── Subsección / Sección ─────────────────────────────────────────────

    private function finalizeSubsection(): void
    {
        $this->finalizeCurrentBlock();

        if ($this->curSub !== null && $this->curSection !== null) {
            // Regla 1: subseccion-regular sin H3 → label directo en sección padre
            if (
                $this->curSub['type'] === 'subseccion-regular'
                && !($this->curSub['has_h3'] ?? true)
            ) {
                $this->curSub['type'] = 'h2-texto-directo';
            }
            $this->curSection['subsections'][] = $this->curSub;
            $this->curSub = null;
        }
    }

    private function finalizeSection(): void
    {
        $this->finalizeSubsection();

        if ($this->curSection !== null) {
            $this->sections[]   = $this->curSection;
            $this->curSection   = null;
        }

        $this->rootFoundInSection = false;
    }

    private function finalizeAll(): void
    {
        $this->finalizeSection();
    }

    // -------------------------------------------------------------------------
    // Utilidades (estáticas para uso externo)
    // -------------------------------------------------------------------------

    /**
     * Elimina marcadores de formato del título exportado desde Google Docs:
     *   - Bold:               **texto** → texto
     *   - Puntuación escapada por Google Docs: \! → !, \[ → [, \] → ], \( → (, etc.
     */
    public static function stripTitleFormatting(string $title): string
    {
        $title = str_replace('**', '', $title);   // quitar bold (**texto**)
        $title = str_replace('*', '', $title);     // quitar italic (*texto*)
        // Desescapar cualquier puntuación escapada por Google Docs Markdown: \! \[ \] \( \) \. \- \# etc.
        $title = preg_replace('/\\\\([^\w\s])/', '$1', $title);
        $title = preg_replace('/^\d+\\\\?\.\\s*/', '', $title);   // quitar prefijo numérico: "1\. " o "1. "
        return trim($title);
    }

    /**
     * Normaliza un título a minúsculas sin tildes para comparaciones.
     * Ej: "Referente Bíblico" → "referente biblico"
     */
    public static function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title), 'UTF-8');
        $from  = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û',
                  'ä','ë','ï','ö'];
        $to    = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u',
                  'a','e','i','o'];
        return str_replace($from, $to, $title);
    }

    /**
     * Normaliza el contenido del archivo antes de parsear.
     */
    private function normalizeContent(string $content): string
    {
        // Normalizar saltos de línea
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        // Comillas tipográficas → ASCII
        $content = str_replace(["\u{201C}", "\u{201D}"], '"', $content);
        $content = str_replace(["\u{2018}", "\u{2019}"], "'", $content);
        // Em-dash
        $content = str_replace("\u{2014}", '--', $content);

        // Eliminar definiciones de imagen base64 exportadas por Google Docs
        // Formato: [image1]: <data:image/TYPE;base64,...>
        $content = preg_replace('/^\[[^\]]+\]:\s*<data:[^>]*>\s*$/m', '', $content);

        // Eliminar referencias estilo ![alt][ref] que apuntaban a esas definiciones
        $content = preg_replace('/!\[[^\]]*\]\[[^\]]+\]/', '', $content);

        // Desescapar imágenes escapadas por Google Docs al exportar a Markdown:
        //   \!\[alt\](url)        → ![alt](url)
        //   \!\[alt\](url){clase} → ![alt](url){clase}
        // La forma nativa (Obsidian, etc.) ya es ![alt](url) y no necesita desescapado.
        $content = preg_replace_callback(
            '/\\\\!\\\\\[(.+?)\\\\\]\(([^)]+)\)(?:\{([^}]+)\})?/',
            static function (array $m): string {
                $suffix = (isset($m[3]) && $m[3] !== '') ? '{' . $m[3] . '}' : '';
                return '![' . $m[1] . '](' . $m[2] . ')' . $suffix;
            },
            $content
        );

        return $content;
    }
}
