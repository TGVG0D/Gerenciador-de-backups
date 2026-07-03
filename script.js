document.addEventListener('DOMContentLoaded', () => {
    // Backups Elements
    const form = document.getElementById('backup-form');
    const dataInput = document.getElementById('data');
    const grid = document.getElementById('backup-grid');
    const modalAdd = document.getElementById('modal-add');
    const btnOpen = document.getElementById('btn-open-modal');
    const btnClose = document.getElementById('btn-close-modal');

    // Categories Elements
    const modalCat = document.getElementById('modal-cat');
    const btnOpenCat = document.getElementById('btn-open-cat-modal');
    const btnCloseCat = document.getElementById('btn-close-cat-modal');
    const catForm = document.getElementById('cat-form');
    
    let allBackups = [];
    let allCategories = [];
    let currentFilterId = null;
    let currentSearchQuery = '';

    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchQuery = e.target.value.toLowerCase();
            renderBackups(allBackups);
        });
    }

    // --- Modal Logic ---
    function openModal(m) { m.classList.add('active'); }
    function closeModal(m) { m.classList.remove('active'); }

    btnOpen.addEventListener('click', () => {
        form.reset();
        document.getElementById('backup-id').value = '';
        dataInput.value = today;
        document.querySelector('#modal-add .modal-header h2').textContent = 'Adicionar Novo Backup';
        openModal(modalAdd);
    });
    btnClose.addEventListener('click', () => closeModal(modalAdd));
    
    btnOpenCat.addEventListener('click', () => {
        catForm.reset();
        document.getElementById('cat-id').value = '';
        document.querySelector('#modal-cat .modal-header h2').textContent = 'Gerenciar Categorias';
        openModal(modalCat);
    });
    btnCloseCat.addEventListener('click', () => closeModal(modalCat));

    [modalAdd, modalCat].forEach(m => {
        m.addEventListener('click', (e) => {
            if (e.target === m) closeModal(m);
        });
    });

    const today = new Date().toISOString().split('T')[0];
    if (dataInput) dataInput.value = today;

    // --- Initialization ---
    init();

    async function init() {
        await loadCategories();
        await loadBackups();
    }

    // --- Categories API & Render ---
    async function loadCategories() {
        try {
            const response = await fetch('api_categorias', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
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
        list.innerHTML = `
            <li style="margin-bottom: 0.5rem;">
                <a href="#" class="cat-link ${currentFilterId === null ? 'active' : ''}" data-id="" style="display: block; padding: 0.5rem; color: #fff; text-decoration: none; border-radius: 6px; background: ${currentFilterId === null ? 'rgba(255,255,255,0.2)' : 'transparent'};">Todas</a>
            </li>
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
                document.getElementById('current-category-title').textContent = `Meus Backups (${title})`;

                renderCategoriesSidebar(); // re-render to update active state
                renderBackups(allBackups); // re-filter
            });
        });
    }

    function buildCategoryItem(cat, level) {
        const isActive = currentFilterId === cat.id;
        const paddingLeft = 0.5 + (level * 1.5);
        const bg = isActive ? 'rgba(255,255,255,0.2)' : 'transparent';
        const bullet = level > 0 ? '↳ ' : '';
        return `
            <li style="margin-bottom: 0.5rem;">
                <a href="#" class="cat-link" data-id="${cat.id}" style="display: block; padding: 0.5rem; padding-left: ${paddingLeft}rem; color: #fff; text-decoration: none; border-radius: 6px; background: ${bg}; transition: background 0.2s;">${bullet}${escapeHTML(cat.nome)}</a>
            </li>
        `;
    }

    function renderCategoriesModalList() {
        const list = document.getElementById('modal-categories-list');
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
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem; background: rgba(0,0,0,0.2); margin-bottom: 0.5rem; border-radius: 6px; margin-left: ${paddingLeft}rem;">
                <span>${bullet}${escapeHTML(cat.nome)}</span>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn-edit" onclick="editCategory('${cat.id}')" title="Renomear/Editar" style="padding: 0.3rem; font-size: 0.8rem;">Editar</button>
                    <button type="button" class="btn-delete" onclick="deleteCategory('${cat.id}')" title="Excluir Categoria" style="padding: 0.3rem; font-size: 0.8rem;">Excluir</button>
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
            const response = await fetch('api', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            allBackups = await response.json();
            renderBackups(allBackups);
        } catch (error) {
            console.error('Erro ao carregar backups:', error);
            grid.innerHTML = '<div class="empty-state">Erro ao carregar dados.</div>';
        }
    }

    function renderBackups(backups) {
        grid.innerHTML = '';

        let filtered = backups;
        if (currentFilterId) {
            // Include backups from the exact category and its subcategories
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
            grid.innerHTML = '<div class="empty-state">Nenhum backup encontrado.</div>';
            return;
        }

        filtered.forEach(backup => {
            const dateObj = new Date(backup.data);
            dateObj.setMinutes(dateObj.getMinutes() + dateObj.getTimezoneOffset());
            const formattedDate = dateObj.toLocaleDateString('pt-BR');
            
            const catName = backup.categoriaId ? (allCategories.find(c => c.id === backup.categoriaId)?.nome || 'Sem Categoria') : 'Sem Categoria';

            const card = document.createElement('div');
            card.className = 'backup-card';
            card.innerHTML = `
                <div class="card-header">
                    <h3 class="card-title">${escapeHTML(backup.nome)}</h3>
                    <span class="card-size">${escapeHTML(backup.tamanho)}</span>
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <span style="font-size: 0.8rem; background: rgba(255,255,255,0.1); padding: 0.2rem 0.5rem; border-radius: 4px;">📂 ${escapeHTML(catName)}</span>
                </div>
                <div style="margin-bottom: 1rem;">
                    <span class="card-date">${formattedDate}</span>
                </div>
                <div class="card-info">
                    ${escapeHTML(backup.informacao).replace(/\n/g, '<br>')}
                </div>
                <div class="card-actions">
                    <a href="${escapeHTML(backup.link)}" target="_blank" rel="noopener noreferrer" class="btn-link">Acessar Link</a>
                    <button class="btn-edit" onclick="editBackup('${backup.id}')" title="Editar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    </button>
                    <button class="btn-delete" onclick="deleteBackup('${backup.id}')" title="Excluir">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    // --- Forms Submit ---
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
            categoriaId: document.getElementById('categoriaId').value
        };

        const id = document.getElementById('backup-id').value;
        if (id) formData.id = id;

        try {
            const response = await fetch('api', {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(formData),
            });
            const result = await response.json();
            if (result.success) {
                closeModal(modalAdd);
                loadBackups();
            } else {
                alert('Erro ao salvar: ' + result.message);
            }
        } catch (error) {
            alert('Erro de conexão.');
        } finally {
            submitBtn.disabled = false;
        }
    });

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
            const response = await fetch('api_categorias', {
                method: id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(formData),
            });
            const result = await response.json();
            if (result.success) {
                catForm.reset();
                closeModal(modalCat); // Fechar modal ao salvar
                await loadCategories();
                loadBackups(); // Refresh backups in case category names changed
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
            
            document.querySelector('#modal-add .modal-header h2').textContent = 'Editar Backup';
            openModal(modalAdd);
        }
    }

    window.deleteBackup = async function(id) {
        if (confirm('Tem certeza que deseja excluir este backup?')) {
            try {
                const response = await fetch('api', {
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
                const response = await fetch('api_categorias', {
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
            document.querySelector('#modal-cat .modal-header h2').textContent = 'Renomear Categoria';
            
            // Scroll para o topo do modal para ver o formulário
            document.querySelector('#modal-cat .modal-content').scrollTop = 0;
        }
    }
});
