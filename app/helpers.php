<?php
/** Funções auxiliares usadas pelas Views. */

use App\Core\Controller;
use App\Models\Chamado;

/** Escapa texto para HTML (proteção contra XSS). */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Controller::tokenCsrf()) . '">';
}

function data_br(?string $d, bool $hora = true): string
{
    if (!$d) return '—';
    return (new DateTime($d))->format($hora ? 'd/m/Y H:i' : 'd/m/Y');
}

function badge_status(string $s): string
{
    return '<span class="badge st-' . strtolower($s) . '">' . e(Chamado::STATUS[$s] ?? $s) . '</span>';
}

function badge_sla(string $s): string
{
    $rot = ['NO_PRAZO' => 'No prazo', 'EM_RISCO' => 'Em risco', 'VIOLADO' => 'Violado', 'ENCERRADO' => 'Encerrado'];
    return '<span class="badge sla-' . strtolower($s) . '">' . e($rot[$s] ?? $s) . '</span>';
}

function badge_prioridade(string $p): string
{
    $k = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $p));
    return '<span class="badge pr-' . e(preg_replace('/[^a-z]/', '', $k)) . '">' . e($p) . '</span>';
}

function tamanho_br(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

function doc_formatado(string $doc): string
{
    return strlen($doc) === 11
        ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc)
        : preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
}

function erro_campo(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? '<p class="erro-campo" role="alert">' . e($erros[$campo]) . '</p>' : '';
}

function sel(bool $c): string { return $c ? ' selected' : ''; }
function chk(bool $c): string { return $c ? ' checked' : ''; }

/** Converte valores do php.ini (ex.: "8M") em bytes. */
function ini_bytes(string $v): int
{
    $v = trim($v);
    $n = (int) $v;
    return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
}

/** Limite real de upload: o menor entre a regra de negócio (10 MB) e o que o servidor aceita. */
function limite_upload_bytes(): int
{
    return min((int) App\Core\App::config('upload_max_bytes'), ini_bytes((string) ini_get('upload_max_filesize')),
               ini_bytes((string) ini_get('post_max_size')) - 65536);
}
