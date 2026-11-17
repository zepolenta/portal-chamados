<?php
/**
 * Configuração da aplicação.
 * Os valores vêm do arquivo .env na raiz do projeto (ver .env.example)
 * ou de variáveis de ambiente do servidor.
 */
$arquivoEnv = dirname(__DIR__) . '/.env';
$vars = [];
if (is_file($arquivoEnv)) {
    foreach (file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $linha, 2));
        $vars[$k] = trim($v, "\"'");
    }
}
// Não usa putenv(): algumas hospedagens compartilhadas desativam essa função
$env = static fn(string $k, $padrao) => $vars[$k] ?? (getenv($k) !== false ? getenv($k) : $padrao);

return [
    'app_nome'     => 'Portal de Chamados Polenta TI',
    'timezone'     => 'America/Sao_Paulo',
    'db' => [
        'host'  => $env('DB_HOST', '127.0.0.1'),
        'porta' => $env('DB_PORT', '3306'),
        'nome'  => $env('DB_NAME', 'portal_chamados'),
        'user'  => $env('DB_USER', 'root'),
        'senha' => $env('DB_PASS', ''),
    ],
    // Expediente usado no cálculo do SLA (horas úteis)
    'expediente' => ['inicio' => 8, 'fim' => 17, 'dias' => [1, 2, 3, 4, 5]],
    'upload_dir'       => dirname(__DIR__) . '/storage/uploads',
    'upload_max_bytes' => 10 * 1024 * 1024,
    'upload_tipos'     => ['image/png', 'image/jpeg', 'image/gif', 'application/pdf', 'text/plain',
                           'application/zip', 'application/x-zip-compressed'],
];
