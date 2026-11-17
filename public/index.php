<?php
/**
 * Front controller: toda requisição passa por aqui e é encaminhada
 * ao Controller/ação correspondente (padrão MVC).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/App.php';
require dirname(__DIR__) . '/app/helpers.php';

App\Core\App::executar();
