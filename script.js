document.addEventListener('DOMContentLoaded', () => {
    // Backups Elements
    const form = document.getElementById('backup-form');
    const dataInput = document.getElementById('data');
    const grid = document.getElementById('backup-grid');
    const modalAdd = document.getElementById('modal-add');
    const modalCat = document.getElementById('modal-cat');
    const catForm = document.getElementById('cat-form');
    
    let allBackups = [];
    let allCategories = [];
    let currentFilterId = null;
    let currentSearchQuery = '';
    let currentPage = 1;
    const itemsPerPage = 9; // Configuração: 9 backups por página

    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.toLowerCase();
            currentPage = 1; // Reseta para a página 1 ao pesquisar
            renderBackups(allBackups);
        });
    }

    // --- Modal Logic ---
    window.openModal = function(type) {
        if (type === 'Adicionar') {
            form.reset();
            document.getElementById('backup-id').value = '';
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('data')) document.getElementById('data').value = today;
            document.getElementById('modal-add-title').textContent = 'Adicionar Backup';
            document.getElementById('cor').value = '#3b82f6';
            document.getElementById('cor-hex').textContent = '#3b82f6';
            modalAdd.classList.add('active');
        } else if (type === 'Categoria') {
            catForm.reset();
            document.getElementById('cat-id').value = '';
            document.getElementById('modal-cat-title').textContent = 'Gerenciar Categorias';
            modalCat.classList.add('active');
        }
    }

    window.closeModal = function() {
        if (modalAdd) modalAdd.classList.remove('active');
        if (modalCat) modalCat.classList.remove('active');
    }

    // Bind close buttons dynamically
    document.querySelectorAll('.modal-close-btn, .modal-close-bg').forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    const btnOpenCat = document.getElementById('btn-open-cat-modal');
    if (btnOpenCat) {
        btnOpenCat.addEventListener('click', () => {
            window.openModal('Categoria');
        });
    }

    const corInput = document.getElementById('cor');
    const corHex = document.getElementById('cor-hex');
    if (corInput && corHex) {
        corInput.addEventListener('input', () => {
            corHex.textContent = corInput.value;
        });
    }

    // --- Initialization ---
    init();

    async function init() {
        await loadCategories();
        await loadBackups();
    }

    // --- Categories API & Render ---
    async function loadCategories() {
        try {
            const response = await fetch('api_categorias.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            allCategories = await response.json();
            renderCategoriesSidebar();
            renderCategoriesModalList();
            populateCategorySelects();
        } catch (error) {
            console.error('Erro ao carregar categorias', error);
        }
    }

    function renderCategoriesSidebar() {
        const list = document.getElementById('categories-list');
        if (!list) return;
        
        const countAll = allBackups.length;
        const isAllActive = currentFilterId === null;
        
        list.innerHTML = `
            <button class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-left cat-link ${isAllActive ? 'bg-primary-container/20 text-primary font-bold border border-primary/20' : 'text-on-surface-variant hover:bg-white/5 transition-all'}" data-id="">
                <span>Todas</span>
                <span class="${isAllActive ? 'bg-primary/20 text-primary' : 'text-[10px] opacity-40'} text-[10px] px-2 py-0.5 rounded-full">${countAll}</span>
            </button>
        `;
        
        // Render main categories
        const mains = allCategories.filter(c => !c.parentId);
        mains.forEach(main => {
            list.innerHTML += buildCategoryItem(main, 0);
            // Render subcategories
            const subs = allCategories.filter(c => c.parentId === main.id);
            subs.forEach(sub => {
                list.innerHTML += buildCategoryItem(sub, 1);
            });
        });

        document.querySelectorAll('.cat-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                currentFilterId = e.currentTarget.getAttribute('data-id') || null;
                
                // Update title
                const title = currentFilterId ? allCategories.find(c => c.id === currentFilterId)?.nome : 'Todos';
                const titleEl = document.getElementById('current-category-title');
                if (titleEl) titleEl.textContent = `Meus Backups (${title})`;

                currentPage = 1; // Reseta para a primeira página ao trocar de categoria
                renderCategoriesSidebar(); // re-render to update active state
                renderBackups(allBackups); // re-filter
            });
        });
    }

    function buildCategoryItem(cat, level) {
        const isActive = currentFilterId === cat.id;
        const paddingLeft = 0.75 + (level * 1); // Indentation for subcategories
        const bullet = level > 0 ? '↳ ' : '';
        const count = allBackups.filter(b => {
            if (b.categoriaId === cat.id) return true;
            if (level === 0) {
                const subs = allCategories.filter(c => c.parentId === cat.id).map(s => s.id);
                return subs.includes(b.categoriaId);
            }
            return false;
        }).length;
        
        return `
            <button class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-left cat-link ${isActive ? 'bg-primary-container/20 text-primary font-bold border border-primary/20' : 'text-on-surface-variant hover:bg-white/5 transition-all'}" data-id="${cat.id}" style="padding-left: ${paddingLeft}rem">
                <span>${bullet}${escapeHTML(cat.nome)}</span>
                <span class="${isActive ? 'bg-primary/20 text-primary' : 'text-[10px] opacity-40'} text-[10px] px-2 py-0.5 rounded-full">${count}</span>
            </button>
        `;
    }

    function renderCategoriesModalList() {
        const list = document.getElementById('modal-categories-list');
        if(!list) return;
        list.innerHTML = '';
        const mains = allCategories.filter(c => !c.parentId);
        mains.forEach(main => {
            list.innerHTML += buildModalCatItem(main, 0);
            const subs = allCategories.filter(c => c.parentId === main.id);
            subs.forEach(sub => {
                list.innerHTML += buildModalCatItem(sub, 1);
            });
        });
    }

    function buildModalCatItem(cat, level) {
        const paddingLeft = level * 1.5;
        const bullet = level > 0 ? '↳ ' : '';
        return `
            <li class="flex justify-between items-center p-2 bg-surface-container rounded-lg border border-glass-border mb-2" style="margin-left: ${paddingLeft}rem;">
                <span class="text-sm font-medium">${bullet}${escapeHTML(cat.nome)}</span>
                <div class="flex gap-2">
                    <button type="button" class="text-primary hover:text-white p-1 rounded hover:bg-primary/20 transition-all" onclick="editCategory('${cat.id}')" title="Renomear/Editar">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>
                    <button type="button" class="text-danger hover:text-white p-1 rounded hover:bg-danger/20 transition-all" onclick="deleteCategory('${cat.id}')" title="Excluir Categoria">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                    </button>
                </div>
            </li>
        `;
    }

    function populateCategorySelects() {
        const selectBackup = document.getElementById('categoriaId');
        const selectParent = document.getElementById('cat-parent');
        
        let optionsHtml = '';
        const mains = allCategories.filter(c => !c.parentId);
        mains.forEach(main => {
            optionsHtml += `<option value="${main.id}">${escapeHTML(main.nome)}</option>`;
            const subs = allCategories.filter(c => c.parentId === main.id);
            subs.forEach(sub => {
                optionsHtml += `<option value="${sub.id}">&nbsp;&nbsp;↳ ${escapeHTML(sub.nome)}</option>`;
            });
        });

        if(selectBackup) selectBackup.innerHTML = '<option value="">Selecione uma categoria...</option>' + optionsHtml;
        if(selectParent) selectParent.innerHTML = '<option value="">Nenhuma (Categoria Principal)</option>' + 
            mains.map(m => `<option value="${m.id}">${escapeHTML(m.nome)}</option>`).join('');
    }

    // --- Backups API & Render ---
    async function loadBackups() {
        try {
            const response = await fetch('api.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            allBackups = await response.json();
            renderCategoriesSidebar(); // Atualiza contadores
            renderBackups(allBackups);
        } catch (error) {
            console.error('Erro ao carregar backups:', error);
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-on-surface-variant">Erro ao carregar dados.</div>';
        }
    }

    function renderBackups(backups) {
        if (!grid) return;
        grid.innerHTML = '';

        let filtered = backups;
        if (currentFilterId) {
            const validCategoryIds = [currentFilterId];
            const subs = allCategories.filter(c => c.parentId === currentFilterId);
            subs.forEach(s => validCategoryIds.push(s.id));
            filtered = filtered.filter(b => validCategoryIds.includes(b.categoriaId));
        }

        if (currentSearchQuery) {
            filtered = filtered.filter(b => 
                (b.nome && b.nome.toLowerCase().includes(currentSearchQuery)) ||
                (b.informacao && b.informacao.toLowerCase().includes(currentSearchQuery))
            );
        }

        if (!filtered || filtered.length === 0) {
            grid.innerHTML = '<div class="col-span-full text-center py-10 text-on-surface-variant flex flex-col items-center gap-2"><span class="material-symbols-outlined text-4xl opacity-50">search_off</span><p>Nenhum backup encontrado.</p></div>';
            renderPagination(0);
            return;
        }

        const totalPages = Math.ceil(filtered.length / itemsPerPage);
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const pageItems = filtered.slice(startIndex, endIndex);

        pageItems.forEach(backup => {
            const dateObj = new Date(backup.data);
            dateObj.setMinutes(dateObj.getMinutes() + dateObj.getTimezoneOffset());
            const formattedDate = dateObj.toLocaleDateString('pt-BR');
            
            const catName = backup.categoriaId ? (allCategories.find(c => c.id === backup.categoriaId)?.nome || 'Sem Categoria') : 'Sem Categoria';
            const corCard = backup.cor || '#3b82f6';
            
            // Lógica para transformar HEX em estilo de badge RGBA (Simulado para cor primária base)
            const isDanger = corCard === '#ef4444' || corCard.toLowerCase() === 'red';
            const isWarning = corCard === '#e67e22' || corCard === '#f59e0b';
            
            let badgeClasses = 'bg-primary/10 text-primary border-primary/20';
            if (isDanger) badgeClasses = 'bg-error/20 text-error border-error/30';
            else if (isWarning) badgeClasses = 'bg-warning/20 text-warning border-warning/30';

            const senhaHtml = backup.senha ? `
                <div class="bg-surface-container-low/50 border border-glass-border rounded-xl p-3 mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]">lock</span>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-on-surface-variant uppercase font-bold">Senha ZIP</span>
                            <span class="font-mono text-sm tracking-widest password-field">••••••</span>
                            <span class="font-mono text-sm hidden password-real text-primary">${escapeHTML(backup.senha)}</span>
                        </div>
                    </div>
                    <button class="text-primary hover:text-white transition-colors flex items-center gap-1 text-xs font-bold px-3 py-1 bg-primary/5 rounded-lg border border-primary/20 hover:bg-primary/20" onclick="togglePass(this)">
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                        <span>Ver</span>
                    </button>
                </div>
            ` : '';

            const card = document.createElement('div');
            card.className = 'glass-panel rounded-2xl overflow-hidden relative group hover:border-primary/40 transition-all duration-300';
            card.innerHTML = `
                <div class="status-bar" style="background-color: ${corCard};"></div>
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <span class="${badgeClasses} text-[10px] px-2 py-1 rounded-md font-bold border">${escapeHTML(catName)}</span>
                        <div class="text-right">
                            <p class="text-[10px] text-on-surface-variant font-medium">${formattedDate}</p>
                            <p class="text-xs font-bold text-on-surface">${escapeHTML(backup.tamanho)}</p>
                        </div>
                    </div>
                    <h4 class="text-lg font-bold text-white mb-2">${escapeHTML(backup.nome)}</h4>
                    <p class="text-sm text-on-surface-variant mb-6 line-clamp-2">${escapeHTML(backup.informacao).replace(/\n/g, '<br>')}</p>
                    
                    ${senhaHtml}
                    
                    <div class="flex items-center gap-2">
                        <a href="${escapeHTML(backup.link)}" target="_blank" rel="noopener noreferrer" class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-white font-bold rounded-xl border border-glass-border flex items-center justify-center gap-2 transition-all no-underline text-center">
                            <span class="material-symbols-outlined text-[20px]">link</span>
                            <span>Acessar Link</span>
                        </a>
                        <button class="p-2.5 bg-primary/10 hover:bg-primary/20 text-primary rounded-xl border border-primary/20 transition-all active:scale-95" onclick="editBackup('${backup.id}')">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </button>
                        <button class="p-2.5 bg-danger/10 hover:bg-danger text-danger hover:text-white rounded-xl border border-danger/20 transition-all active:scale-95" onclick="deleteBackup('${backup.id}')">
                            <span class="material-symbols-outlined text-[20px]">delete</span>
                        </button>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
        
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('pagination-container');
        const nav = document.getElementById('pagination-nav');
        if (!container || !nav) return;

        if (totalPages <= 1) {
            container.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        nav.innerHTML = '';

        // Botão Anterior
        const prevBtn = document.createElement('button');
        prevBtn.className = 'w-10 h-10 rounded-lg flex items-center justify-center hover:bg-white/5 transition-colors disabled:opacity-30 text-on-surface-variant';
        prevBtn.disabled = currentPage === 1;
        prevBtn.innerHTML = '<span class="material-symbols-outlined">chevron_left</span>';
        prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderBackups(allBackups); } };
        nav.appendChild(prevBtn);

        // Botões de Páginas
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            if (i === currentPage) {
                btn.className = 'w-10 h-10 rounded-lg flex items-center justify-center bg-primary text-on-primary font-bold shadow-md shadow-primary/20';
            } else {
                btn.className = 'w-10 h-10 rounded-lg flex items-center justify-center hover:bg-white/5 text-on-surface-variant font-bold transition-colors';
            }
            btn.textContent = i;
            btn.onclick = () => { currentPage = i; renderBackups(allBackups); };
            nav.appendChild(btn);
        }

        // Botão Próximo
        const nextBtn = document.createElement('button');
        nextBtn.className = 'w-10 h-10 rounded-lg flex items-center justify-center hover:bg-white/5 transition-colors disabled:opacity-30 text-on-surface-variant';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.innerHTML = '<span class="material-symbols-outlined">chevron_right</span>';
        nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderBackups(allBackups); } };
        nav.appendChild(nextBtn);
    }

    // --- Forms Submit ---
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('.btn-submit');
            submitBtn.disabled = true;

            const formData = {
                nome: document.getElementById('nome').value,
                link: document.getElementById('link').value,
                data: document.getElementById('data').value,
                tamanho: document.getElementById('tamanho').value,
                informacao: document.getElementById('informacao').value,
                categoriaId: document.getElementById('categoriaId').value,
                senha: document.getElementById('senha').value,
                cor: document.getElementById('cor').value,
            };

            const id = document.getElementById('backup-id').value;
            if (id) formData.id = id;

            try {
                const response = await fetch('api.php', {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(formData),
                });
                const result = await response.json();
                if (result.success) {
                    closeModal();
                    loadBackups();
                } else {
                    alert('Erro ao salvar: ' + result.message);
                }
            } catch (error) {
                alert('Erro de conexão ao salvar backup.');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    if (catForm) {
        catForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = catForm.querySelector('.btn-submit');
            submitBtn.disabled = true;

            const formData = {
                nome: document.getElementById('cat-nome').value,
                parentId: document.getElementById('cat-parent').value
            };

            const id = document.getElementById('cat-id').value;
            if (id) formData.id = id;

            try {
                const response = await fetch('api_categorias.php', {
                    method: id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(formData),
                });
                const result = await response.json();
                if (result.success) {
                    catForm.reset();
                    closeModal();
                    await loadCategories();
                    loadBackups();
                } else {
                    alert('Erro ao salvar: ' + result.message);
                }
            } catch (error) {
                console.error(error);
                alert('Erro de conexão ao salvar categoria.');
            } finally {
                submitBtn.disabled = false;
            }
        });
    }

    function escapeHTML(str) {
        if(!str) return '';
        return str.replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    // --- Global Functions for inline onclick ---
    window.editBackup = function(id) {
        const backup = allBackups.find(b => b.id === id);
        if (backup) {
            document.getElementById('backup-id').value = backup.id;
            document.getElementById('nome').value = backup.nome;
            document.getElementById('link').value = backup.link;
            document.getElementById('data').value = backup.data;
            document.getElementById('tamanho').value = backup.tamanho;
            document.getElementById('informacao').value = backup.informacao;
            document.getElementById('categoriaId').value = backup.categoriaId || '';
            document.getElementById('senha').value = backup.senha || '';
            document.getElementById('cor').value = backup.cor || '#3b82f6';
            document.getElementById('cor-hex').textContent = backup.cor || '#3b82f6';
            
            document.getElementById('modal-add-title').textContent = 'Editar Backup';
            window.openModal('Adicionar');
        }
    }

    window.deleteBackup = async function(id) {
        if (confirm('Tem certeza que deseja excluir este backup?')) {
            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id }),
                });
                const result = await response.json();
                if(result.success) loadBackups();
                else alert('Erro: ' + result.message);
            } catch(error) {
                alert('Erro de conexão.');
            }
        }
    }

    window.deleteCategory = async function(id) {
        if (confirm('Excluir esta categoria também excluirá suas subcategorias. Tem certeza?')) {
            try {
                const response = await fetch('api_categorias.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ id }),
                });
                const result = await response.json();
                if(result.success) {
                    await loadCategories();
                    loadBackups();
                } else alert('Erro: ' + result.message);
            } catch(error) {
                alert('Erro de conexão.');
            }
        }
    }

    window.editCategory = function(id) {
        const cat = allCategories.find(c => c.id === id);
        if (cat) {
            document.getElementById('cat-id').value = cat.id;
            document.getElementById('cat-nome').value = cat.nome;
            document.getElementById('cat-parent').value = cat.parentId || '';
            document.getElementById('modal-cat-title').textContent = 'Renomear Categoria';
            window.openModal('Categoria');
        }
    }

    window.togglePass = function(btn) {
        const container = btn.parentElement;
        const field = container.querySelector('.password-field');
        const real = container.querySelector('.password-real');
        const icon = btn.querySelector('.material-symbols-outlined');
        const text = btn.querySelector('span:not(.material-symbols-outlined)');
        
        if (field.classList.contains('hidden')) {
            field.classList.remove('hidden');
            real.classList.add('hidden');
            icon.textContent = 'visibility';
            text.textContent = 'Ver';
        } else {
            field.classList.add('hidden');
            real.classList.remove('hidden');
            icon.textContent = 'visibility_off';
            text.textContent = 'Ocultar';
        }
    }
});
