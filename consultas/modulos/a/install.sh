#!/bin/bash

###############################################################################
# ZIIPVET - SCRIPT DE INSTALAÇÃO DA MODULARIZAÇÃO
# Versão: 1.0.0
# Autor: Sistema ZiipVet
# Data: Janeiro 2025
###############################################################################

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                                                                ║"
echo "║       🚀 ZIIPVET - INSTALADOR DE MODULARIZAÇÃO 🚀            ║"
echo "║                                                                ║"
echo "║  Este script irá reorganizar o sistema para arquitetura      ║"
echo "║  modular, tornando o código mais limpo e sustentável.        ║"
echo "║                                                                ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variáveis
APP_DIR="/var/www/html/app"
CONSULTAS_DIR="$APP_DIR/consultas"
MODULOS_DIR="$CONSULTAS_DIR/modulos"
BACKUP_DIR="$CONSULTAS_DIR/backup_$(date +%Y%m%d_%H%M%S)"
TEMP_DIR="/tmp/modulos"

# Função de log
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[AVISO]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERRO]${NC} $1"
}

# Verificar se está rodando como root ou com permissões adequadas
check_permissions() {
    log_info "Verificando permissões..."
    if [ ! -w "$CONSULTAS_DIR" ]; then
        log_error "Sem permissão de escrita em $CONSULTAS_DIR"
        log_warning "Execute como: sudo bash install.sh"
        exit 1
    fi
    log_success "Permissões OK"
}

# Criar backup
create_backup() {
    log_info "Criando backup de segurança..."
    mkdir -p "$BACKUP_DIR"
    
    if [ -f "$CONSULTAS_DIR/realizar_consulta.php" ]; then
        cp "$CONSULTAS_DIR/realizar_consulta.php" "$BACKUP_DIR/"
        log_success "Backup criado em: $BACKUP_DIR"
    else
        log_error "Arquivo realizar_consulta.php não encontrado!"
        exit 1
    fi
}

# Criar estrutura de diretórios
create_structure() {
    log_info "Criando estrutura de diretórios..."
    mkdir -p "$MODULOS_DIR"
    log_success "Pasta modulos/ criada"
}

# Copiar módulos
copy_modules() {
    log_info "Copiando módulos..."
    
    if [ ! -d "$TEMP_DIR" ]; then
        log_error "Pasta temporária $TEMP_DIR não encontrada!"
        log_warning "Execute os arquivos de criação primeiro."
        exit 1
    fi
    
    # Lista de arquivos para copiar
    modules=(
        "_shared_styles.php"
        "_shared_scripts.php"
        "_sidebar_historico.php"
        "_modal_modelo.php"
        "atendimento.php"
        "patologia.php"
        "exames.php"
        "vacinas.php"
        "receitas.php"
        "documentos.php"
        "diagnostico_ia.php"
    )
    
    for module in "${modules[@]}"; do
        if [ -f "$TEMP_DIR/$module" ]; then
            cp "$TEMP_DIR/$module" "$MODULOS_DIR/"
            log_success "✓ $module"
        else
            log_warning "✗ $module não encontrado (pulando)"
        fi
    done
}

# Substituir arquivo principal
replace_main_file() {
    log_info "Substituindo arquivo principal..."
    
    if [ -f "/tmp/realizar_consulta_modularizado.php" ]; then
        cp "/tmp/realizar_consulta_modularizado.php" "$CONSULTAS_DIR/realizar_consulta.php"
        log_success "Arquivo principal atualizado"
    else
        log_error "Arquivo modularizado não encontrado!"
        exit 1
    fi
}

# Ajustar permissões
fix_permissions() {
    log_info "Ajustando permissões..."
    
    # Permissões para arquivos PHP
    chmod 644 "$MODULOS_DIR"/*.php
    chmod 644 "$CONSULTAS_DIR/realizar_consulta.php"
    
    # Proprietário (ajuste conforme seu servidor)
    # chown www-data:www-data -R "$MODULOS_DIR"
    # chown www-data:www-data "$CONSULTAS_DIR/realizar_consulta.php"
    
    log_success "Permissões ajustadas"
}

# Verificação pós-instalação
verify_installation() {
    log_info "Verificando instalação..."
    
    errors=0
    
    # Verificar se todos os módulos existem
    required_files=(
        "$MODULOS_DIR/_shared_styles.php"
        "$MODULOS_DIR/_shared_scripts.php"
        "$MODULOS_DIR/atendimento.php"
        "$MODULOS_DIR/diagnostico_ia.php"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            log_error "Arquivo não encontrado: $file"
            ((errors++))
        fi
    done
    
    if [ $errors -eq 0 ]; then
        log_success "Todos os arquivos verificados!"
    else
        log_error "Encontrados $errors erros. Verifique a instalação."
        return 1
    fi
}

# Menu de confirmação
confirm_installation() {
    echo ""
    log_warning "ATENÇÃO: Esta operação irá modificar arquivos do sistema!"
    log_warning "Um backup será criado em: $BACKUP_DIR"
    echo ""
    read -p "Deseja continuar? (s/N): " -n 1 -r
    echo ""
    
    if [[ ! $REPLY =~ ^[Ss]$ ]]; then
        log_info "Instalação cancelada pelo usuário."
        exit 0
    fi
}

# Rollback em caso de erro
rollback() {
    log_error "Erro detectado! Iniciando rollback..."
    
    if [ -d "$BACKUP_DIR" ]; then
        cp "$BACKUP_DIR/realizar_consulta.php" "$CONSULTAS_DIR/"
        rm -rf "$MODULOS_DIR"
        log_success "Sistema restaurado para estado anterior"
    fi
    
    exit 1
}

# Função principal
main() {
    echo ""
    log_info "Iniciando instalação da modularização..."
    echo ""
    
    # Executar etapas
    check_permissions || rollback
    confirm_installation
    create_backup || rollback
    create_structure || rollback
    copy_modules || rollback
    replace_main_file || rollback
    fix_permissions || rollback
    verify_installation || rollback
    
    echo ""
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                                                                ║"
    echo "║              ✅ INSTALAÇÃO CONCLUÍDA COM SUCESSO! ✅          ║"
    echo "║                                                                ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo ""
    log_success "Sistema modularizado instalado!"
    log_info "Backup salvo em: $BACKUP_DIR"
    echo ""
    log_warning "PRÓXIMOS PASSOS:"
    echo "  1. Acesse: https://www.lepetboutique.com.br/app/consultas/realizar_consulta.php"
    echo "  2. Teste todas as funcionalidades"
    echo "  3. Verifique a nova aba 'Diagnóstico IA'"
    echo "  4. Em caso de problemas, restaure o backup"
    echo ""
    log_info "Para restaurar backup:"
    echo "  cp $BACKUP_DIR/realizar_consulta.php $CONSULTAS_DIR/"
    echo "  rm -rf $MODULOS_DIR"
    echo ""
}

# Executar
main
