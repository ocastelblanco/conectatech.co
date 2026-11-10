# Faceta `python-web`

> CC BY-SA 4.0. Fuentes: Input Validation, File Upload, Deserialization, Denial of Service, FastAPI Security.

| Chequeo | Qué revisar |
|---|---|
| PY-03 servicios internos | Si el servicio (OCR, IA) no tiene autenticación, la única defensa es la red: confirma que en el compose de producción **no publica puertos** y que solo lo consume otro servicio interno. Si publica puertos, `fail`. |
| PY-04 procesamiento pesado | `pdf2image` con `last_page`, timeout de gunicorn (`--timeout`), límite de páginas, `MAX_CONTENT_LENGTH`. |
| BASE-14 debug | `app.run(debug=True)` o `FLASK_ENV=development` en el compose que va a producción. |
| BASE-15 CORS | `CORS(app)` sin `origins=` permite todo. En servicios internos, CORS sobra: quítalo. |
