<?php

// Servidor falso de la API de Anthropic para pruebas (php -S … tests/Fixtures/api-anthropic-falsa.php).
// Guarda la petición recibida y responde lo que indique el escenario.
$dir = getenv('API_FALSA_DIR');
$escenario = trim((string) @file_get_contents("$dir/escenario")) ?: 'ok';
file_put_contents("$dir/peticion.json", json_encode([
    'ruta' => $_SERVER['REQUEST_URI'],
    'cabeceras' => array_change_key_case(getallheaders(), CASE_LOWER),
    'cuerpo' => json_decode(file_get_contents('php://input'), true),
]));

header('Content-Type: application/json');
$base = ['id' => 'msg_falso', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
    'stop_sequence' => null, 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]];

switch ($escenario) {
    case 'rechazo':
        echo json_encode($base + ['content' => [], 'stop_reason' => 'refusal']);
        break;
    case 'error500':
        http_response_code(500);
        echo json_encode(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'falla']]);
        break;
    case 'json_roto':
        echo json_encode($base + ['content' => [['type' => 'text', 'text' => 'no es json']], 'stop_reason' => 'end_turn']);
        break;
    default:
        echo json_encode($base + ['stop_reason' => 'end_turn', 'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => json_encode(['respuesta' => 'Tu saldo de noviembre es $2,035.00.', 'requiere_asesor' => false])],
        ]]);
}
