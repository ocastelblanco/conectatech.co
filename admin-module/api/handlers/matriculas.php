<?php
/**
 * handlers/matriculas.php — Handler para matriculación masiva de usuarios.
 *
 * POST /api/matriculas
 */

// ─────────────────────────────────────────────────────────────────────────────
// POST /api/matriculas
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea o actualiza usuarios en Moodle y los matricula en sus cursos.
 *
 * El frontend envía el array resultante de convertir el Excel con ExcelJS.
 * La operación es idempotente: ejecutarla dos veces produce el mismo estado.
 *
 * Lógica de usuario:
 *   - Si NO existe → se crea con todos los datos, incluida la contraseña.
 *   - Si ya existe → se actualiza perfil (la contraseña NO se modifica).
 *
 * Lógica de cursos:
 *   - student → solo cursos cuyo shortname termina en "-{grado}".
 *   - teacher → todos los cursos del colegio sin filtro de grado.
 *
 * Request body:
 *   {
 *     "dry_run": false,
 *     "usuarios": [
 *       {
 *         "username":    "jperez",
 *         "password":    "Pass1234!",
 *         "firstname":   "Juan",
 *         "lastname":    "Pérez",
 *         "email":       "jperez@sanmarino.edu.co",
 *         "institution": "San Marino",
 *         "rol":         "student",
 *         "grado":       6
 *       }
 *     ]
 *   }
 *
 * Response 200:
 *   { ok: bool, dry_run: bool,
 *     summary: { created: int, updated: int, errors: int },
 *     results: [{ username, action, user_id, courses, error }] }
 */
function handleMatriculas(): void
{
    $body = readJsonBody();

    if (empty($body['usuarios']) || !is_array($body['usuarios'])) {
        badRequest("Se requiere 'usuarios' como array no vacío.");
    }

    $dryRun  = (bool)($body['dry_run'] ?? false);
    $service = new MatriculasService();

    $results = [];
    $summary = ['created' => 0, 'updated' => 0, 'errors' => 0];

    foreach ($body['usuarios'] as $usuario) {
        try {
            $result = $service->matricularUsuario($usuario, $dryRun);
        } catch (Throwable $e) {
            $result = [
                'username' => $usuario['username'] ?? '',
                'action'   => 'error',
                'user_id'  => null,
                'courses'  => [],
                'error'    => $e->getMessage(),
            ];
        }

        match (true) {
            in_array($result['action'], ['created', 'dry-run:create']) => $summary['created']++,
            in_array($result['action'], ['updated', 'dry-run:update']) => $summary['updated']++,
            $result['action'] === 'error'                              => $summary['errors']++,
            default                                                    => null,
        };

        $results[] = $result;
    }

    echo json_encode([
        'ok'      => $summary['errors'] === 0,
        'dry_run' => $dryRun,
        'summary' => $summary,
        'results' => $results,
    ]);
}
