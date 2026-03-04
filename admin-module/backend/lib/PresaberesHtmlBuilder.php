<?php
/**
 * PresaberesHtmlBuilder.php
 * Genera el HTML interactivo para subsecciones [presaberes].
 *
 * El HTML generado no contiene <script> ni <style> (HTMLPurifier de Moodle
 * los eliminaría). La interactividad la aporta el JS global en Boost Union.
 *
 * Modos de feedback:
 *   - "data-attribute" : data-feedback="texto" en el div de la opción
 *   - "hidden-span"    : <span class="option-feedback visually-hidden">texto</span>
 *
 * IDs únicos: {shortname}-s{N}-sub{M}-q{Q}-opt{P}
 */

class PresaberesHtmlBuilder
{
    private string $feedbackMode;
    private string $courseShortname;

    public function __construct(string $feedbackMode = 'data-attribute', string $courseShortname = 'curso')
    {
        $this->feedbackMode    = $feedbackMode;
        $this->courseShortname = $courseShortname;
    }

    // -------------------------------------------------------------------------
    // Generación del HTML completo para una subsección [presaberes]
    // -------------------------------------------------------------------------

    /**
     * Genera el HTML completo del label de una subsección presaberes.
     *
     * @param array  $preguntaBlocks  Array de bloques del parser (curSub['pregunta_blocks'])
     * @param int    $sectionIndex    Índice 1-based de la sección (H1) en el curso
     * @param int    $subIndex        Índice 1-based de la subsección dentro de la sección
     * @return string                 HTML concatenado de todos los bloques de pregunta
     */
    public function buildHtml(array $preguntaBlocks, int $sectionIndex, int $subIndex): string
    {
        $html = '';

        foreach ($preguntaBlocks as $qIndex => $block) {
            $questionNumber = $qIndex + 1;
            $html .= $this->buildQuestionBlock($block, $sectionIndex, $subIndex, $questionNumber);
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Bloque individual de pregunta
    // -------------------------------------------------------------------------

    private function buildQuestionBlock(
        array $block,
        int $sectionIndex,
        int $subIndex,
        int $questionNumber
    ): string {
        $ariaLabel = htmlspecialchars($block['aria_label'], ENT_QUOTES, 'UTF-8');
        $baseId    = sprintf(
            '%s-s%d-sub%d-q%d',
            $this->courseShortname,
            $sectionIndex,
            $subIndex,
            $questionNumber
        );

        $html = <<<HTML
<div class="moodle-quiz-container p-3 mb-4 border rounded bg-white shadow-sm"
     data-quiz-block
     aria-label="{$ariaLabel}">

HTML;

        // Contexto (si existe)
        if (!empty($block['context'])) {
            $contextHtml = $this->textToHtml(trim($block['context']));
            $html .= <<<HTML
  <div class="mb-3">
    <p class="mb-0 lead">{$contextHtml}</p>
  </div>

HTML;
        }

        // Pregunta y opciones
        if (!empty($block['pregunta'])) {
            $html .= $this->buildFieldset($block['pregunta'], $baseId);
        }

        // Área de retroalimentación (llenada por JS)
        $html .= <<<HTML

  <div class="retroalimentacion mt-3" aria-live="polite"></div>

</div>

HTML;

        return $html;
    }

    // -------------------------------------------------------------------------
    // Fieldset con pregunta y opciones
    // -------------------------------------------------------------------------

    private function buildFieldset(array $pregunta, string $baseId): string
    {
        $enunciado = htmlspecialchars($pregunta['enunciado'], ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
  <fieldset class="border-0 p-0">
    <legend class="h5 font-weight-bold mb-3 text-dark">
      {$enunciado}
    </legend>

    <div class="options-group">

HTML;

        foreach ($pregunta['options'] as $optIndex => $option) {
            $optNumber = $optIndex + 1;
            $optId     = $baseId . '-opt' . $optNumber;
            $html     .= $this->buildOption($option, $baseId, $optId);
        }

        $html .= <<<HTML
    </div>
  </fieldset>
HTML;

        return $html;
    }

    // -------------------------------------------------------------------------
    // Opción de respuesta individual
    // -------------------------------------------------------------------------

    private function buildOption(array $option, string $groupName, string $optId): string
    {
        $dataType     = $option['correct'] ? 'correct' : 'incorrect';
        $feedbackText = htmlspecialchars($option['feedback'], ENT_QUOTES, 'UTF-8');
        $optText      = htmlspecialchars($option['text'],     ENT_QUOTES, 'UTF-8');

        // Atributo data-feedback según modo configurado
        $feedbackAttr   = '';
        $feedbackHidden = '';

        if ($this->feedbackMode === 'data-attribute') {
            $feedbackAttr = ' data-feedback="' . $feedbackText . '"';
        } else {
            // hidden-span: JS lee querySelector('.option-feedback')
            $feedbackHidden = "\n        <span class=\"option-feedback visually-hidden\">{$feedbackText}</span>";
        }

        return <<<HTML
      <div class="respuesta-option custom-control custom-radio mb-3 p-2 rounded border border-light"
           data-type="{$dataType}"{$feedbackAttr}>
        <input type="radio" id="{$optId}"
               name="{$groupName}" class="custom-control-input" value="{$optId}">
        <label class="custom-control-label w-100" for="{$optId}">
          <span class="option-text">{$optText}</span>{$feedbackHidden}
        </label>
      </div>

HTML;
    }

    // -------------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------------

    /**
     * Convierte texto plano a HTML escapando y conservando saltos de línea como <br>.
     * Para el contexto de las preguntas (texto simple, no markdown complejo).
     */
    private function textToHtml(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // Convertir saltos de línea a <br> (para multiline context)
        return str_replace("\n", '<br>', $text);
    }

    // -------------------------------------------------------------------------
    // Setters
    // -------------------------------------------------------------------------

    public function setFeedbackMode(string $mode): void
    {
        $this->feedbackMode = $mode;
    }

    public function setCourseShortname(string $shortname): void
    {
        $this->courseShortname = $shortname;
    }
}
