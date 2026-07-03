<?php
require_once 'auth.php';
checkLogin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Backups</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?=time()?>">
</head>
<body>
    <div class="container">
        <header>
            <h1><span class="highlight">G</span>erenciador de <span class="highlight">B</span>ackups</h1>
            <p>Agrupe e organize seus links de backup facilmente.</p>
            <div style="display: flex; gap: 1rem; margin-top: 1rem; justify-content: center;">
                <a href="profile.php" class="btn-logout" style="background: rgba(255,255,255,0.1); color: #fff; text-decoration: none;">Perfil</a>
                <a href="auth.php?action=logout" class="btn-logout" style="text-decoration: none;">Sair</a>
            </div>
        </header>

        <div class="layout-wrapper" style="display: flex; gap: 2rem; margin-top: 2rem;">
            <!-- Sidebar de Categorias -->
            <aside class="categories-sidebar glass" style="width: 250px; padding: 1.5rem; border-radius: 12px; height: fit-content;">
                <h2 style="font-size: 1.2rem; margin-bottom: 1rem; color: #fff;">Categorias</h2>
                <ul id="categories-list" style="list-style: none; padding: 0; margin: 0; margin-bottom: 1.5rem;">
                    <!-- Categorias carregadas via JS -->
                </ul>
                <button id="btn-open-cat-modal" class="btn-submit" style="width: 100%; font-size: 0.9rem; padding: 0.6rem;">Gerenciar Categorias</button>
            </aside>

            <main style="flex: 1;">
                <div class="actions-header" style="display: flex; justify-content: flex-end; margin-bottom: 2rem;">
                    <button id="btn-open-modal" class="btn-submit" style="width: auto;">+ Adicionar Backup</button>
                </div>

                <!-- Modal de Backup -->
                <div id="modal-add" class="modal-overlay">
                    <section class="form-section glass modal-content">
                        <div class="modal-header">
                            <h2>Adicionar Novo Backup</h2>
                            <button id="btn-close-modal" class="btn-close">&times;</button>
                        </div>
                        <form id="backup-form">
                            <input type="hidden" id="backup-id" name="id">
                            <div class="form-group">
                                <label for="nome">Nome do Backup</label>
                                <input type="text" id="nome" name="nome" placeholder="Ex: Backup do Site X" required>
                            </div>
                            <div class="form-group">
                                <label for="categoriaId">Categoria</label>
                                <select id="categoriaId" name="categoriaId" required>
                                    <option value="">Selecione uma categoria...</option>
                                    <!-- Opções preenchidas via JS -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="link">Link do Backup</label>
                                <input type="url" id="link" name="link" placeholder="https://..." required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="data">Data do Backup</label>
                                    <input type="date" id="data" name="data" required>
                                </div>
                                <div class="form-group">
                                    <label for="tamanho">Tamanho</label>
                                    <input type="text" id="tamanho" name="tamanho" placeholder="Ex: 500 MB" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="informacao">Informação / Descrição</label>
                                <textarea id="informacao" name="informacao" rows="3" placeholder="Detalhes sobre o conteúdo do backup..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Salvar Backup</button>
                        </form>
                    </section>
                </div>

                <!-- Modal de Gerenciar Categorias -->
                <div id="modal-cat" class="modal-overlay">
                    <section class="form-section glass modal-content">
                        <div class="modal-header">
                            <h2>Gerenciar Categorias</h2>
                            <button id="btn-close-cat-modal" class="btn-close">&times;</button>
                        </div>
                        <form id="cat-form">
                            <input type="hidden" id="cat-id" name="id">
                            <div class="form-group">
                                <label for="cat-nome">Nome da Categoria</label>
                                <input type="text" id="cat-nome" name="nome" required>
                            </div>
                            <div class="form-group">
                                <label for="cat-parent">Categoria Pai (Subcategoria)</label>
                                <select id="cat-parent" name="parentId">
                                    <option value="">Nenhuma (Categoria Principal)</option>
                                    <!-- Preenchido via JS -->
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">Salvar Categoria</button>
                        </form>
                        <hr style="border: 1px solid rgba(255,255,255,0.1); margin: 1.5rem 0;">
                        <h3>Categorias Existentes</h3>
                        <ul id="modal-categories-list" style="list-style: none; padding: 0; margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                            <!-- Preenchido via JS -->
                        </ul>
                    </section>
                </div>

                <section class="list-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 id="current-category-title" style="margin: 0;">Meus Backups (Todos)</h2>
                        <div class="search-container" style="position: relative; width: 300px;">
                            <input type="text" id="search-input" placeholder="Pesquisar backups..." style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.2); color: #fff; font-family: 'Inter', sans-serif;">
                            <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5);" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </div>
                    </div>
                    <div id="backup-grid" class="backup-grid">
                        <!-- Os backups serão carregados aqui via JS -->
                    </div>
                </section>
            </main>
        </div>
    </div>
    <script src="script.js?v=<?=time()?>"></script>
</body>
</html>
