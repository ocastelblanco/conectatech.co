# Faceta `llm-integration`

> CC BY-SA 4.0. Fuentes: LLM Prompt Injection Prevention, AI Agent Security, RAG Security.

ASVS 5.0 no tiene un capítulo de LLM. LLM-01 y LLM-03 se marcan `asvs_gap` y se verifican contra la cheat sheet; los demás se apoyan en requisitos ASVS de inyección, acceso y disponibilidad.

| Chequeo | Qué revisar |
|---|---|
| LLM-01 separación | Prompt de sistema fijo; el contenido externo (documentos OCR, mensajes de Telegram, webs) entra delimitado como datos y nunca se interpola en las instrucciones. |
| LLM-02 salida no confiable | La respuesta se valida contra un esquema JSON y se codifica antes de HTML, SQL o publicación. Nada de `eval` ni comandos con la salida. |
| LLM-03 acciones | Publicar en redes, enviar correo o borrar requiere aprobación humana. En n8n: un nodo de aprobación (Telegram o Wait) antes de la acción. |
| LLM-04 datos personales | Cédulas u hojas de vida enviadas a una API externa (Gemini u OpenAI) requieren autorización bajo la Ley 1581. Con modelo local (Ollama), confirma que el puerto 11434 no está expuesto (CTR-01). |
| LLM-05 límites | `max_tokens` o `num_predict`, timeout y límite por ejecución o usuario. |
