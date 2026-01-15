<?php

namespace App\Application\Service;

use App\Infrastructure\Repository\ClienteRepository;
use Exception;
use PDOException;

class ClienteService
{
    private ClienteRepository $clienteRepository;

    public function __construct(ClienteRepository $clienteRepository)
    {
        $this->clienteRepository = $clienteRepository;
    }

    /**
     * Lista clientes com paginação
     *
     * @param string $busca Termo de busca
     * @param int $pagina Página atual
     * @param int $itensPorPagina Itens por página
     * @return array ['clientes' => array, 'total_registros' => int, 'total_paginas' => int, 'pagina_atual' => int]
     */
    public function listarPaginado(string $busca = '', int $pagina = 1, int $itensPorPagina = 20): array
    {
        $offset = ($pagina - 1) * $itensPorPagina;
        
        $totalRegistros = $this->clienteRepository->contar($busca);
        $totalPaginas = ceil($totalRegistros / $itensPorPagina);
        $clientes = $this->clienteRepository->listar($busca, $offset, $itensPorPagina);

        return [
            'clientes' => $clientes,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'pagina_atual' => $pagina
        ];
    }

    /**
     * Busca um cliente por ID
     *
     * @param int $id
     * @return array|null
     */
    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }

        return $this->clienteRepository->buscarPorId($id);
    }

    /**
     * Exclui um cliente
     *
     * @param int $id
     * @return array ['success' => bool, 'message' => string]
     */
    public function excluir(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'ID inválido.'
            ];
        }

        try {
            $this->clienteRepository->excluir($id);
            
            return [
                'success' => true,
                'message' => 'Cliente excluído com sucesso!'
            ];
        } catch (PDOException $e) {
            // Erro de constraint (cliente tem vínculos)
            return [
                'success' => false,
                'message' => 'Erro: existem animais ou histórico clínico vinculados.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao processar requisição.'
            ];
        }
    }

    /**
     * Cria um novo cliente
     *
     * @param array $dados
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function criar(array $dados): array
    {
        try {
            // Validações básicas
            if (empty($dados['nome'])) {
                return [
                    'success' => false,
                    'message' => 'Nome é obrigatório.',
                    'id' => null
                ];
            }

            $id = $this->clienteRepository->criar($dados);
            
            return [
                'success' => true,
                'message' => 'Cliente criado com sucesso!',
                'id' => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar cliente: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }

    /**
     * Atualiza um cliente existente
     *
     * @param int $id
     * @param array $dados
     * @return array ['success' => bool, 'message' => string]
     */
    public function atualizar(int $id, array $dados): array
    {
        try {
            if ($id <= 0) {
                return [
                    'success' => false,
                    'message' => 'ID inválido.'
                ];
            }

            // Validações básicas
            if (empty($dados['nome'])) {
                return [
                    'success' => false,
                    'message' => 'Nome é obrigatório.'
                ];
            }

            $this->clienteRepository->atualizar($id, $dados);
            
            return [
                'success' => true,
                'message' => 'Cliente atualizado com sucesso!'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar cliente: ' . $e->getMessage()
            ];
        }
    }
}
