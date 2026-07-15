<?php
require_once 'auth.php';
checkLogin();

// Pegar info do admin
$env = readEnv(__DIR__ . '/.env');
$adminName = $env['APP_USER'] ?? 'Admin Root';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Gerenciador de Backups - Gerenciar Backups</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#bcc7de",
                        "error": "#ffb4ab",
                        "on-primary-container": "#00285d",
                        "surface-container-low": "#191b23",
                        "background": "#10131a",
                        "outline-variant": "#424754",
                        "surface-dim": "#10131a",
                        "on-tertiary-container": "#461f00",
                        "surface-container-high": "#272a31",
                        "primary": "#adc6ff",
                        "tertiary-fixed-dim": "#ffb786",
                        "on-error": "#690005",
                        "inverse-on-surface": "#2e3038",
                        "surface": "#10131a",
                        "primary-container": "#4d8eff",
                        "inverse-surface": "#e1e2ec",
                        "error-container": "#93000a",
                        "surface-tint": "#adc6ff",
                        "on-surface-variant": "#c2c6d6",
                        "on-primary": "#002e6a",
                        "on-secondary-container": "#aeb9d0",
                        "surface-container-highest": "#32353c",
                        "glass-border": "rgba(255, 255, 255, 0.1)",
                        "secondary-container": "#3e495d",
                        "tertiary-container": "#df7412",
                        "on-surface": "#e1e2ec",
                        "hover-primary": "#2563eb",
                        "primary-fixed": "#d8e2ff",
                        "inverse-primary": "#005ac2",
                        "on-tertiary-fixed-variant": "#723600",
                        "on-tertiary-fixed": "#311400",
                        "on-primary-fixed-variant": "#004395",
                        "on-background": "#e1e2ec",
                        "danger": "#ef4444",
                        "on-secondary": "#263143",
                        "secondary-fixed": "#d8e3fb",
                        "bg-base": "#0f172a",
                        "warning": "#e67e22",
                        "tertiary": "#ffb786",
                        "secondary": "#bcc7de",
                        "primary-fixed-dim": "#adc6ff",
                        "on-secondary-fixed": "#111c2d",
                        "surface-variant": "#32353c",
                        "tertiary-fixed": "#ffdcc6",
                        "on-secondary-fixed-variant": "#3c475a",
                        "on-primary-fixed": "#001a42",
                        "surface-bright": "#363941",
                        "surface-container": "#1d2027",
                        "outline": "#8c909f",
                        "text-primary": "#f8fafc",
                        "surface-container-lowest": "#0b0e15",
                        "on-tertiary": "#502400",
                        "on-error-container": "#ffdad6",
                        "glass-bg": "rgba(30, 41, 59, 0.7)",
                        "success": "#2ecc71"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "gap-sm": "1rem",
                        "gap-lg": "2rem",
                        "gutter-grid": "16px",
                        "margin-page": "24px",
                        "gap-xs": "0.5rem",
                        "gap-md": "1.5rem"
                    },
                    "fontFamily": {
                        "body-base": ["Inter"],
                        "headline-md": ["Inter"],
                        "label-sm": ["Inter"],
                        "display-lg": ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #10131a;
            font-family: 'Inter', sans-serif;
            color: #e1e2ec;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .status-bar {
            width: 4px;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        /* Modal Overlay fix */
        .modal-overlay {
            display: none;
        }
        .modal-overlay.active {
            display: flex;
        }
        /* Loading Spinner */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="overflow-x-hidden">

<!-- Ambient Background Animation -->
<div class="fixed inset-0 z-[-1] overflow-hidden">
    <!-- Optional bg animations could go here -->
</div>

<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-64 flex flex-col p-gap-sm z-40 bg-glass-bg dark:bg-glass-bg backdrop-blur-md border-r border-glass-border">
    <div class="flex items-center gap-3 mb-10 px-2">
        <div class="w-10 h-10 rounded-lg bg-primary flex items-center justify-center shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined text-on-primary">shield</span>
        </div>
        <div>
            <h1 class="font-headline-md text-headline-md font-extrabold text-primary">Gerenciador</h1>
            <p class="text-[10px] uppercase tracking-widest text-on-surface-variant font-bold">Backup Seguro</p>
        </div>
    </div>
    
    <nav class="flex-1 space-y-2">
        <!-- Dashboard -->
        <a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg group dev-feature" href="#">
            <span class="material-symbols-outlined transition-transform group-active:translate-x-1">dashboard</span>
            <span class="font-label-sm text-label-sm font-medium">Dashboard</span>
        </a>
        
        <!-- Backups (ACTIVE) -->
        <a class="flex items-center gap-3 px-4 py-3 bg-primary-container text-on-primary-container rounded-lg font-bold transition-transform active:translate-x-1" href="index.php">
            <span class="material-symbols-outlined">backup</span>
            <span class="font-label-sm text-label-sm">Backups</span>
        </a>
        
        <!-- Segurança -->
        <a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg group dev-feature" href="#">
            <span class="material-symbols-outlined transition-transform group-active:translate-x-1">shield</span>
            <span class="font-label-sm text-label-sm font-medium">Segurança</span>
        </a>
        
        <!-- Configurações -->
        <a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-colors duration-300 rounded-lg group" href="settings.php">
            <span class="material-symbols-outlined transition-transform group-active:translate-x-1">settings</span>
            <span class="font-label-sm text-label-sm font-medium">Configurações</span>
        </a>
    </nav>
    
    <div class="mt-auto pt-6 border-t border-glass-border">
        <button id="btn-open-modal" class="w-full flex items-center justify-center gap-2 py-3 bg-primary hover:bg-hover-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 transition-all duration-300 active:scale-95">
            <span class="material-symbols-outlined">add_circle</span>
            <span>Novo Backup</span>
        </button>
        
        <div class="mt-6 flex items-center gap-3 px-2">
            <div class="w-10 h-10 rounded-full border border-glass-border overflow-hidden bg-surface-container flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold truncate"><?php echo htmlspecialchars($adminName); ?></p>
                <p class="text-xs text-on-surface-variant truncate">Premium Plan</p>
            </div>
            <a href="auth.php?action=logout" class="text-on-surface-variant hover:text-error transition-colors" title="Sair">
                <span class="material-symbols-outlined">logout</span>
            </a>
        </div>
    </div>
</aside>

<!-- TopAppBar -->
<header class="flex justify-between items-center w-full px-margin-page py-4 backdrop-blur-xl fixed top-0 z-30 bg-glass-bg dark:bg-glass-bg border-b border-glass-border md:pl-[18rem]">
    <h2 id="current-category-title" class="font-display-lg text-display-lg font-bold text-primary">Gerenciador de Backups</h2>
    <div class="flex items-center gap-4">
        <button class="p-2 rounded-full hover:bg-white/10 transition-all duration-300 active:scale-95 text-on-surface-variant dev-feature">
            <span class="material-symbols-outlined">notifications</span>
        </button>
        <a href="settings.php" class="flex items-center gap-2 cursor-pointer hover:bg-white/10 p-1 px-2 rounded-lg transition-all">
            <span class="material-symbols-outlined text-primary">account_circle</span>
            <span class="text-on-surface font-medium hidden sm:inline">Perfil</span>
        </a>
    </div>
</header>

<!-- Main Content -->
<main class="md:pl-64 pt-24 min-h-screen">
    <div class="p-margin-page">
        <!-- Section Header & Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-white">Gerenciar Backups</h1>
                <p class="text-on-surface-variant">Monitore e controle seus arquivos de redundância.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative group">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">search</span>
                    <input id="search-input" class="bg-surface-container border border-glass-border rounded-xl pl-10 pr-4 py-2.5 w-64 md:w-80 focus:border-primary focus:ring-0 transition-all outline-none text-on-surface" placeholder="Pesquisar backups..." type="text"/>
                </div>
                <!-- Optional duplicated add button -->
                <button class="bg-[#3b82f6] hover:bg-blue-600 text-white px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 shadow-lg shadow-blue-500/20 transition-all active:scale-95" onclick="document.getElementById('btn-open-modal').click();">
                    <span class="material-symbols-outlined">add</span>
                    <span>Adicionar Backup</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-gap-lg">
            <!-- Sidebar Interna Categorias -->
            <aside class="w-full lg:w-64 space-y-6">
                <div class="glass-panel rounded-2xl p-4">
                    <h3 class="text-xs uppercase tracking-widest font-bold text-on-surface-variant mb-4 px-2">Categorias</h3>
                    <nav id="categories-list" class="space-y-1">
                        <!-- Loaded via JS -->
                    </nav>
                    <button id="btn-open-cat-modal" class="w-full mt-6 py-2 px-3 border border-glass-border rounded-lg text-xs font-bold text-on-surface-variant hover:text-on-surface hover:bg-white/5 transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">category</span>
                        Gerenciar Categorias
                    </button>
                </div>
                
                <div class="glass-panel rounded-2xl p-4 dev-feature" style="cursor:pointer;" title="Recurso em desenvolvimento">
                    <h3 class="text-xs uppercase tracking-widest font-bold text-on-surface-variant mb-4 px-2">Uso de Espaço</h3>
                    <div class="h-2 w-full bg-surface-container rounded-full overflow-hidden mb-2">
                        <div class="h-full bg-primary w-[65%]"></div>
                    </div>
                    <div class="flex justify-between text-[11px] font-medium text-on-surface-variant">
                        <span>1.2 TB Usado</span>
                        <span>2.0 TB Total</span>
                    </div>
                </div>
            </aside>

            <!-- Grid de Backups -->
            <div class="flex-1">
                <div id="backup-grid" class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-6">
                    <!-- Cards Loaded via JS -->
                </div>
                
                <!-- Pagination -->
                <div id="pagination-container" class="mt-12 flex justify-center hidden">
                    <nav id="pagination-nav" class="flex items-center gap-2 glass-panel p-2 rounded-2xl">
                        <!-- Loaded via JS -->
                    </nav>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal de Adicionar/Editar Backup -->
<div id="modal-add" class="fixed inset-0 z-[100] modal-overlay items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm modal-close-bg"></div>
    <div class="glass-panel w-full max-w-lg rounded-2xl overflow-hidden relative shadow-2xl animate-in fade-in zoom-in duration-300">
        
        <div class="flex items-center justify-between p-6 border-b border-glass-border">
            <h2 class="text-xl font-bold text-white" id="modal-add-title">Adicionar Backup</h2>
            <button class="text-on-surface-variant hover:text-white transition-colors modal-close-btn">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="backup-form" class="p-6 space-y-4 overflow-y-auto max-h-[80vh]">
            <input type="hidden" id="backup-id" name="id">
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="nome">Nome do Backup</label>
                <input id="nome" name="nome" required class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface" placeholder="Ex: Backup Semanal DB" type="text"/>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="categoriaId">Categoria</label>
                <select id="categoriaId" name="categoriaId" class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface">
                    <option value="">Selecione uma categoria...</option>
                    <!-- Opções preenchidas via JS -->
                </select>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="link">Link do Backup</label>
                <input id="link" name="link" required class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface" placeholder="https://..." type="url"/>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="data">Data do Backup</label>
                    <input id="data" name="data" required class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface" type="date"/>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="tamanho">Tamanho</label>
                    <input id="tamanho" name="tamanho" required class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface" placeholder="Ex: 500 MB" type="text"/>
                </div>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="informacao">Informação / Descrição (opcional)</label>
                <textarea id="informacao" name="informacao" class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface h-24 resize-none"></textarea>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="senha">🔑 Senha do ZIP (opcional)</label>
                <div class="relative">
                    <input id="senha" name="senha" class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 pr-12 focus:border-primary focus:ring-0 outline-none text-on-surface" type="password" placeholder="Vazio se não houver senha" autocomplete="off"/>
                    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary" type="button" onclick="var i=document.getElementById('senha'); i.type=i.type==='password'?'text':'password'; this.querySelector('.material-symbols-outlined').textContent=i.type==='password'?'visibility':'visibility_off';">
                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                    </button>
                </div>
            </div>
            
            <div class="space-y-1">
                <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="cor">🎨 Cor do Card (opcional)</label>
                <div class="flex items-center gap-3">
                    <input id="cor" name="cor" class="w-12 h-10 bg-surface-container border border-glass-border rounded-lg p-1 cursor-pointer" type="color" value="#3b82f6"/>
                    <span id="cor-hex" class="text-sm font-mono text-on-surface-variant">#3b82f6</span>
                    <button class="text-xs font-bold text-primary hover:underline" type="button" onclick="document.getElementById('cor').value='#3b82f6';document.getElementById('cor-hex').textContent='#3b82f6';">Reset</button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit w-full py-3 bg-primary hover:bg-hover-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-95 mt-4">
                Salvar Backup
            </button>
        </form>
    </div>
</div>

<!-- Modal de Gerenciar Categorias -->
<div id="modal-cat" class="fixed inset-0 z-[100] modal-overlay items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm modal-close-bg"></div>
    <div class="glass-panel w-full max-w-lg rounded-2xl overflow-hidden relative shadow-2xl animate-in fade-in zoom-in duration-300">
        
        <div class="flex items-center justify-between p-6 border-b border-glass-border">
            <h2 class="text-xl font-bold text-white" id="modal-cat-title">Gerenciar Categorias</h2>
            <button class="text-on-surface-variant hover:text-white transition-colors modal-close-btn">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[80vh]">
            <form id="cat-form" class="space-y-4 mb-6">
                <input type="hidden" id="cat-id" name="id">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="cat-nome">Nome da Categoria</label>
                    <input id="cat-nome" name="nome" required class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface" type="text"/>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider" for="cat-parent">Categoria Pai (Subcategoria)</label>
                    <select id="cat-parent" name="parentId" class="w-full bg-surface-container border border-glass-border rounded-xl px-4 py-2.5 focus:border-primary focus:ring-0 outline-none text-on-surface">
                        <option value="">Nenhuma (Categoria Principal)</option>
                        <!-- Preenchido via JS -->
                    </select>
                </div>
                <button type="submit" class="btn-submit w-full py-3 bg-primary hover:bg-hover-primary text-on-primary font-bold rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-95">
                    Salvar Categoria
                </button>
            </form>
            
            <hr class="border-glass-border my-6">
            
            <h3 class="text-sm font-bold text-white mb-4">Categorias Existentes</h3>
            <ul id="modal-categories-list" class="space-y-2">
                <!-- Preenchido via JS -->
            </ul>
        </div>
    </div>
</div>

<script src="script.js?v=<?=time()?>"></script>

<script>
    // Alerta de funcionalidade em desenvolvimento
    document.querySelectorAll('.dev-feature').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            alert('Esta funcionalidade está em desenvolvimento e será lançada em breve!');
        });
    });

    // Micro-interações para botões
    document.querySelectorAll('button').forEach(button => {
        button.addEventListener('mousedown', () => {
            button.style.transform = 'scale(0.95)';
        });
        button.addEventListener('mouseup', () => {
            button.style.transform = 'scale(1)';
        });
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'scale(1)';
        });
    });
</script>

</body>
</html>
