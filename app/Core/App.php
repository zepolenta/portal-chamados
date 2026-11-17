<?php
namespace App\Core;

/** Inicialização, configuração e roteamento (front controller). */
final class App
{
    private static array $config = [];

    /** Rotas: "controller/acao" => [Classe, método] */
    private const ROTAS = [
        'auth/login'          => ['AuthController', 'login'],
        'auth/sair'           => ['AuthController', 'sair'],
        'painel/index'        => ['PainelController', 'index'],
        'chamados/index'      => ['ChamadoController', 'index'],
        'chamados/novo'       => ['ChamadoController', 'novo'],
        'chamados/ver'        => ['ChamadoController', 'ver'],
        'chamados/responder'  => ['ChamadoController', 'responder'],
        'chamados/status'     => ['ChamadoController', 'status'],
        'chamados/assumir'    => ['ChamadoController', 'assumir'],
        'chamados/avaliar'    => ['ChamadoController', 'avaliar'],
        'chamados/anexo'      => ['ChamadoController', 'anexo'],
        'clientes/index'      => ['ClienteController', 'index'],
        'clientes/form'       => ['ClienteController', 'form'],
        'usuarios/index'      => ['UsuarioController', 'index'],
        'usuarios/form'       => ['UsuarioController', 'form'],
    ];

    public static function config(?string $chave = null)
    {
        if (!self::$config) self::$config = require dirname(__DIR__, 2) . '/config/config.php';
        return $chave === null ? self::$config : (self::$config[$chave] ?? null);
    }

    public static function iniciar(): void
    {
        date_default_timezone_set(self::config('timezone'));
        spl_autoload_register(static function (string $classe) {
            $base = dirname(__DIR__);
            $mapa = ['App\\Core\\' => '/Core/', 'App\\Controllers\\' => '/controllers/',
                     'App\\Models\\' => '/models/', 'App\\Services\\' => '/services/'];
            foreach ($mapa as $prefixo => $pasta) {
                if (str_starts_with($classe, $prefixo)) {
                    $arq = $base . $pasta . substr($classe, strlen($prefixo)) . '.php';
                    if (is_file($arq)) require $arq;
                    return;
                }
            }
        });
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
            session_start();
        }
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
    }

    public static function executar(): void
    {
        self::iniciar();
        $rota = $_GET['r'] ?? (Auth::check() ? 'painel/index' : 'auth/login');
        if (!isset(self::ROTAS[$rota])) {
            http_response_code(404);
            View::render('erros/404', ['titulo' => 'Página não encontrada']);
            return;
        }
        [$classe, $metodo] = self::ROTAS[$rota];
        $fqcn = 'App\\Controllers\\' . $classe;
        try {
            (new $fqcn())->$metodo();
        } catch (\Throwable $e) {
            error_log($e);
            http_response_code(500);
            View::render('erros/404', ['titulo' => 'Erro interno',
                'mensagem' => 'Ocorreu um erro inesperado. Tente novamente ou contate o suporte.']);
        }
    }
}
