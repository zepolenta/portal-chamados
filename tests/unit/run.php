<?php
/**
 * Testes unitários (sem dependências): regras de SLA e validação de CPF/CNPJ.
 * Uso: php tests/unit/run.php
 */
declare(strict_types=1);

require __DIR__ . '/../../app/services/ServicoSla.php';
require __DIR__ . '/../../app/services/ValidadorDocumento.php';

use App\Services\ServicoSla;
use App\Services\ValidadorDocumento as V;

date_default_timezone_set('America/Sao_Paulo');
$ok = 0; $falhas = [];
function verificar(string $nome, $obtido, $esperado): void
{
    global $ok, $falhas;
    if ($obtido === $esperado) { $ok++; echo "  [OK]    $nome\n"; }
    else { $falhas[] = $nome; echo "  [FALHA] $nome — esperado " . var_export($esperado, true) . ', obtido ' . var_export($obtido, true) . "\n"; }
}
$sla = new ServicoSla(8, 17, [1, 2, 3, 4, 5]);
$d = fn(string $s) => new DateTimeImmutable($s);
$f = fn(DateTimeImmutable $x) => $x->format('Y-m-d H:i');

echo "ServicoSla\n";
verificar('Crítica (4h) aberta seg 09:00 vence seg 13:00', $f($sla->somarMinutosUteis($d('2026-11-16 09:00'), 240)), '2026-11-16 13:00');
verificar('Crítica aberta seg 15:00 vence ter 10:00 (atravessa a noite)', $f($sla->somarMinutosUteis($d('2026-11-16 15:00'), 240)), '2026-11-17 10:00');
verificar('Aberta sex 16:00 com 2h vence seg 09:00 (pula fim de semana)', $f($sla->somarMinutosUteis($d('2026-11-20 16:00'), 120)), '2026-11-23 09:00');
verificar('Aberta sáb 10:00 começa a contar seg 08:00', $f($sla->somarMinutosUteis($d('2026-11-21 10:00'), 60)), '2026-11-23 09:00');
verificar('Aberta antes do expediente (06:30) conta a partir das 08:00', $f($sla->somarMinutosUteis($d('2026-11-16 06:30'), 60)), '2026-11-16 09:00');
verificar('Aberta após o expediente (19:00) conta no dia seguinte', $f($sla->somarMinutosUteis($d('2026-11-16 19:00'), 60)), '2026-11-17 09:00');
verificar('Baixa (36h úteis = 4 dias) seg 08:00 vence qui 17:00', $f($sla->somarMinutosUteis($d('2026-11-16 08:00'), 2160)), '2026-11-19 17:00');
verificar('Minutos úteis entre sex 16:00 e seg 09:00 = 120', $sla->minutosUteisEntre($d('2026-11-20 16:00'), $d('2026-11-23 09:00')), 120);
verificar('Situação: prazo vencido = VIOLADO', $sla->situacao('ABERTO', $d('2026-11-16 10:00'), 240, $d('2026-11-16 11:00')), 'VIOLADO');
verificar('Situação: resta 30 de 240 min = EM_RISCO', $sla->situacao('EM_ATENDIMENTO', $d('2026-11-16 12:00'), 240, $d('2026-11-16 11:30')), 'EM_RISCO');
verificar('Situação: resta 180 de 240 min = NO_PRAZO', $sla->situacao('ABERTO', $d('2026-11-16 12:00'), 240, $d('2026-11-16 09:00')), 'NO_PRAZO');
verificar('Situação: chamado resolvido = ENCERRADO', $sla->situacao('RESOLVIDO', $d('2026-11-01 12:00'), 240, $d('2026-11-16 09:00')), 'ENCERRADO');

echo "ValidadorDocumento\n";
verificar('CPF válido 529.982.247-25', V::cpfValido('529.982.247-25'), true);
verificar('CPF com dígito errado', V::cpfValido('529.982.247-24'), false);
verificar('CPF com dígitos repetidos', V::cpfValido('111.111.111-11'), false);
verificar('CNPJ válido 11.222.333/0001-81', V::cnpjValido('11.222.333/0001-81'), true);
verificar('CNPJ com dígito errado', V::cnpjValido('11.222.333/0001-82'), false);
verificar('CNPJ com tamanho errado', V::cnpjValido('1122233300018'), false);
verificar('PF exige CPF (CNPJ informado é inválido)', V::valido('PF', '11222333000181'), false);

$total = $ok + count($falhas);
echo "\nResultado: $ok de $total testes aprovados.\n";
exit($falhas ? 1 : 0);
