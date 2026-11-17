<?php
namespace App\Models;

use App\Core\App;

final class Anexo extends Model
{
    /**
     * Valida um arquivo de $_FILES. Retorna [dados, null] ou [null, mensagem de erro].
     * Arquivo ausente retorna [null, null].
     */
    public static function validarUpload(?array $f): array
    {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
        $max = limite_upload_bytes();
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > $max) {
            return [null, 'O anexo excede o limite de ' . tamanho_br($max) . '.'];
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return [null, 'Não foi possível receber o anexo. Tente novamente.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
        if (!in_array($mime, App::config('upload_tipos'), true)) {
            return [null, 'Tipo de arquivo não permitido. Envie imagem (PNG, JPG, GIF), PDF, TXT ou ZIP.'];
        }
        $nome = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($f['name'])), 0, 200);
        return [['tmp' => $f['tmp_name'], 'nome' => $nome, 'mime' => $mime, 'tamanho' => (int) $f['size']], null];
    }

    /** Move o arquivo para o armazenamento e registra no banco. */
    public static function criar(int $idInteracao, array $a): int
    {
        $sub = date('Y/m');
        $dir = App::config('upload_dir') . '/' . $sub;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $ext = strtolower(pathinfo($a['nome'], PATHINFO_EXTENSION));
        $chave = $sub . '/' . bin2hex(random_bytes(16)) . ($ext ? ".$ext" : '');
        if (!move_uploaded_file($a['tmp'], App::config('upload_dir') . '/' . $chave)) {
            throw new \RuntimeException('Falha ao gravar o anexo.');
        }
        self::executar('INSERT INTO anexo (id_interacao, nome_arquivo, caminho, tipo_mime, tamanho_bytes) VALUES (?,?,?,?,?)',
            [$idInteracao, $a['nome'], $chave, $a['mime'], $a['tamanho']]);
        return (int) self::db()->lastInsertId();
    }

    public static function comChamado(int $idAnexo): ?array
    {
        return self::um('SELECT a.*, i.id_chamado, i.interna FROM anexo a JOIN interacao i ON i.id_interacao = a.id_interacao
                         WHERE a.id_anexo = ?', [$idAnexo]);
    }
}
