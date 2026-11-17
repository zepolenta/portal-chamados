<?php
namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Cálculo de prazos de SLA em horário útil (padrão: seg–sex, 8h–17h).
 * Classe pura (sem banco) para facilitar testes unitários.
 */
final class ServicoSla
{
    public function __construct(
        private int $horaInicio = 8,
        private int $horaFim = 17,
        private array $diasUteis = [1, 2, 3, 4, 5],
    ) {}

    public static function padrao(): self
    {
        $e = \App\Core\App::config('expediente');
        return new self($e['inicio'], $e['fim'], $e['dias']);
    }

    /** Soma minutos úteis a partir de uma data/hora. */
    public function somarMinutosUteis(DateTimeInterface $inicio, int $minutos): DateTimeImmutable
    {
        $atual = $this->ajustarParaExpediente(DateTimeImmutable::createFromInterface($inicio));
        $restante = $minutos;
        while ($restante > 0) {
            $fimDia = $atual->setTime($this->horaFim, 0);
            $disponivel = intdiv($fimDia->getTimestamp() - $atual->getTimestamp(), 60);
            if ($restante <= $disponivel) {
                return $atual->modify("+{$restante} minutes");
            }
            $restante -= $disponivel;
            $atual = $this->ajustarParaExpediente($fimDia->modify('+1 day')->setTime($this->horaInicio, 0));
        }
        return $atual;
    }

    /** Minutos úteis entre duas datas (negativo se $fim for anterior a $inicio). */
    public function minutosUteisEntre(DateTimeInterface $inicio, DateTimeInterface $fim): int
    {
        if ($fim < $inicio) return -$this->minutosUteisEntre($fim, $inicio);
        $a = $this->ajustarParaExpediente(DateTimeImmutable::createFromInterface($inicio));
        $b = DateTimeImmutable::createFromInterface($fim);
        $total = 0;
        while ($a < $b) {
            $fimDia = $a->setTime($this->horaFim, 0);
            $limite = $b < $fimDia ? $b : $fimDia;
            if ($limite > $a) $total += intdiv($limite->getTimestamp() - $a->getTimestamp(), 60);
            $a = $this->ajustarParaExpediente($fimDia->modify('+1 day')->setTime($this->horaInicio, 0));
        }
        return $total;
    }

    /** Calcula os prazos de resposta e solução de um chamado. */
    public function calcularPrazos(DateTimeInterface $abertura, int $minResposta, int $minSolucao): array
    {
        return [
            'prazo_resposta' => $this->somarMinutosUteis($abertura, $minResposta),
            'prazo_solucao'  => $this->somarMinutosUteis($abertura, $minSolucao),
        ];
    }

    /**
     * Situação do SLA de solução: ENCERRADO, VIOLADO, EM_RISCO (≤ 20% do prazo restante) ou NO_PRAZO.
     */
    public function situacao(string $status, DateTimeInterface $prazoSolucao, int $minSolucao,
                             ?DateTimeInterface $agora = null): string
    {
        if (in_array($status, ['RESOLVIDO', 'FECHADO', 'CANCELADO'], true)) return 'ENCERRADO';
        $agora ??= new DateTimeImmutable();
        if ($agora > $prazoSolucao) return 'VIOLADO';
        $restante = $this->minutosUteisEntre($agora, $prazoSolucao);
        return $restante <= $minSolucao * 0.2 ? 'EM_RISCO' : 'NO_PRAZO';
    }

    /** Leva a data para o próximo instante dentro do expediente. */
    private function ajustarParaExpediente(DateTimeImmutable $d): DateTimeImmutable
    {
        for ($i = 0; $i < 14; $i++) {
            $diaSemana = (int) $d->format('N');
            $inicio = $d->setTime($this->horaInicio, 0);
            $fim = $d->setTime($this->horaFim, 0);
            if (in_array($diaSemana, $this->diasUteis, true)) {
                if ($d < $inicio) return $inicio;
                if ($d < $fim) return $d->setTime((int) $d->format('H'), (int) $d->format('i'));
            }
            $d = $d->modify('+1 day')->setTime($this->horaInicio, 0);
        }
        return $d;
    }
}
