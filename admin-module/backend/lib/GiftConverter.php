<?php
/**
 * GiftConverter.php
 * Convierte preguntas del árbol del parser al formato GIFT de Moodle.
 *
 * Tipos soportados:
 *   - opcion-multiple : genera GIFT con = (correcta), ~ (incorrecta), # (feedback)
 *
 * Tipos futuros (reconocidos con warning, no generan GIFT):
 *   - verdadero-falso, emparejamiento, respuesta-corta, numerica, ensayo (variante adjunto)
 *
 * Tipo ensayo (texto): no genera GIFT — se crea vía PHP API en MoodleContentBuilder.
 *
 * Referencia formato GIFT:
 *   https://docs.moodle.org/en/GIFT_format
 */

class GiftConverter
{
    // Tipos futuros que el parser debe reconocer pero no implementar
    private const FUTURE_TYPES = ['verdadero-falso', 'emparejamiento', 'respuesta-corta', 'numerica'];

    // -------------------------------------------------------------------------
    // Entrada pública
    // -------------------------------------------------------------------------

    /**
     * Convierte un array de preguntas (de subseccion-evaluacion) a texto GIFT.
     * Las preguntas tipo 'ensayo' se omiten del GIFT (se crearán vía PHP API).
     *
     * @param array  $questions     Array de preguntas del parser
     * @param string $categoryName  Nombre de la categoría de preguntas en Moodle
     * @param string $courseShortname  Shortname del curso (para IDs únicos)
     * @param int    $sectionIndex  Índice de la sección (para IDs únicos)
     * @return string               Texto GIFT completo
     */
    public function convertQuestions(
        array  $questions,
        string $categoryName,
        string $courseShortname,
        int    $sectionIndex
    ): string {
        $gift = '';

        // Cabecera de categoría GIFT
        $gift .= '$CATEGORY: ' . $this->escapeName($categoryName) . "\n\n";

        foreach ($questions as $qIndex => $question) {
            $questionNumber = $qIndex + 1;
            $questionId     = sprintf('%s-s%d-q%d', $courseShortname, $sectionIndex, $questionNumber);

            $gift .= $this->convertQuestion($question, $questionId);
        }

        return $gift;
    }

    // -------------------------------------------------------------------------
    // Conversión de una pregunta individual
    // -------------------------------------------------------------------------

    private function convertQuestion(array $question, string $questionId): string
    {
        $tipo = strtolower(trim($question['tipo']));

        switch ($tipo) {
            case 'opcion-multiple':
                return $this->convertMultipleChoice($question, $questionId);

            case 'ensayo':
                // Se crea vía PHP API en MoodleContentBuilder — no genera GIFT
                return '';

            default:
                // Tipo futuro o desconocido
                if (in_array($tipo, self::FUTURE_TYPES)) {
                    error_log("WARN: Tipo de pregunta '{$tipo}' aún no implementado (futuro). "
                        . "Pregunta omitida: '{$question['title']}'");
                } else {
                    error_log("WARN: Tipo de pregunta desconocido '{$tipo}'. "
                        . "Pregunta omitida: '{$question['title']}'");
                }
                return '';
        }
    }

    // -------------------------------------------------------------------------
    // Opción múltiple
    // -------------------------------------------------------------------------

    private function convertMultipleChoice(array $question, string $questionId): string
    {
        $title     = $this->escapeGift($question['title']);
        $enunciado = $this->escapeGift($question['enunciado']);

        // El texto de la pregunta es el enunciado si existe, o el título como fallback
        $questionText = !empty(trim($question['enunciado'])) ? $enunciado : $title;

        // Verificar que hay opciones
        if (empty($question['options'])) {
            error_log("WARN: Pregunta opcion-multiple sin opciones: '{$question['title']}'");
            return '';
        }

        $hasCorrect = false;
        foreach ($question['options'] as $opt) {
            if ($opt['correct']) {
                $hasCorrect = true;
                break;
            }
        }

        if (!$hasCorrect) {
            error_log("WARN: Pregunta sin opción correcta marcada: '{$question['title']}'");
        }

        // Cabecera de la pregunta
        $gift = "::{$questionId}::{$questionText} {\n";

        // Opciones
        foreach ($question['options'] as $option) {
            $optText  = $this->escapeGift($option['text']);
            $feedback = !empty($option['feedback']) ? ' #' . $this->escapeGift($option['feedback']) : '';
            $prefix   = $option['correct'] ? '=' : '~';
            $gift    .= "  {$prefix}{$optText}{$feedback}\n";
        }

        $gift .= "}\n\n";

        return $gift;
    }

    // -------------------------------------------------------------------------
    // Escapado GIFT
    // -------------------------------------------------------------------------

    /**
     * Escapa caracteres especiales del formato GIFT.
     * Los caracteres { } = ~ # : son especiales en GIFT.
     */
    private function escapeGift(string $text): string
    {
        $text = trim($text);
        // Escapar caracteres especiales GIFT con backslash
        $text = str_replace(['\\'], ['\\\\'], $text);
        $text = str_replace(['{', '}', '=', '~', '#', ':'], ['\\{', '\\}', '\\=', '\\~', '\\#', '\\:'], $text);
        return $text;
    }

    /**
     * Escapa el nombre de la categoría GIFT (no lleva los mismos escapes).
     */
    private function escapeName(string $name): string
    {
        return str_replace(['/', '\\'], ['-', '-'], $name);
    }

    // -------------------------------------------------------------------------
    // Extracción de preguntas tipo ensayo (para MoodleContentBuilder)
    // -------------------------------------------------------------------------

    /**
     * Retorna solo las preguntas tipo 'ensayo' de un array de preguntas.
     * MoodleContentBuilder las usará para crear preguntas Essay vía PHP API.
     */
    public function extractEssayQuestions(array $questions): array
    {
        return array_filter($questions, function (array $q): bool {
            return strtolower($q['tipo']) === 'ensayo';
        });
    }

    /**
     * Retorna las preguntas que sí generan GIFT (no ensayo).
     */
    public function extractGiftQuestions(array $questions): array
    {
        return array_filter($questions, function (array $q): bool {
            $tipo = strtolower($q['tipo']);
            return $tipo !== 'ensayo';
        });
    }
}
