<?php

namespace App\Application\Service;

use Exception;

/**
 * =========================================================================================
 * ZIIPVET - SECURE FILE UPLOADER SERVICE
 * CLASSE: FileUploaderService
 * DESCRIÇÃO: Serviço centralizado para uploads seguros de arquivos
 * =========================================================================================
 */
class FileUploaderService
{
    /**
     * Realiza o upload seguro de um arquivo
     * 
     * @param array $file O array do $_FILES['campo']
     * @param string $destinationDir Diretório de destino (relativo à raiz ou absoluto)
     * @param array $allowedMimeTypes Lista de tipos MIME permitidos
     * @return string Nome do arquivo gerado
     * @throws Exception Caso ocorra algum erro de segurança ou processamento
     */
    public function upload(array $file, string $destinationDir, array $allowedMimeTypes): string
    {
        // 1. Verificar erros nativos do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getErrorMessage($file['error']));
        }

        // 2. Verificar se o diretório existe e tem permissão
        if (!is_dir($destinationDir)) {
            if (!mkdir($destinationDir, 0755, true)) {
                throw new Exception("Não foi possível criar o diretório de destino.");
            }
        }

        if (!is_writable($destinationDir)) {
            throw new Exception("O diretório de destino não tem permissão de escrita.");
        }

        // 3. Validação rigorosa de Tipo MIME (Não confia na extensão ou no campo 'type' do $_FILES)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($actualMimeType, $allowedMimeTypes)) {
            throw new Exception("Tipo de arquivo não permitido: " . $actualMimeType);
        }

        // 4. Gerar nome de arquivo seguro (Hash + Extensão Original baseada no mime)
        $extension = $this->getExtensionFromMime($actualMimeType);
        $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newFileName;

        // 5. Mover arquivo para o destino final
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Falha ao mover o arquivo para o destino.");
        }

        return $newFileName;
    }

    /**
     * Mapeia tipos mime comuns para extensões
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'text/xml' => 'xml',
            'application/xml' => 'xml',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'text/plain' => 'txt'
        ];

        return $map[$mimeType] ?? 'bin';
    }

    /**
     * Traduz códigos de erro de upload do PHP
     */
    private function getErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:   return "O arquivo excede o limite definido no servidor.";
            case UPLOAD_ERR_FORM_SIZE:  return "O arquivo excede o limite definido no formulário.";
            case UPLOAD_ERR_PARTIAL:    return "O upload foi feito apenas parcialmente.";
            case UPLOAD_ERR_NO_FILE:    return "Nenhum arquivo foi enviado.";
            case UPLOAD_ERR_NO_TMP_DIR: return "Pasta temporária ausente no servidor.";
            case UPLOAD_ERR_CANT_WRITE: return "Falha ao escrever arquivo no disco.";
            default: return "Erro desconhecido no upload.";
        }
    }
}
