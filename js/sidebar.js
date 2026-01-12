document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarMinimize = document.getElementById('sidebarMinimize');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const sidebarSearch = document.getElementById('sidebarSearch');
    const searchResults = document.getElementById('searchResults');

    // تهيئة بيانات البحث
    const menuItems = Array.from(document.querySelectorAll('.sidebar-item-card')).map(item => {
        const link = item.querySelector('a');
        const labelNode = item.querySelector('.sidebar-item-label');
        const subNode = item.querySelector('.sidebar-item-sub');
        
        return {
            element: item,
            label: labelNode ? labelNode.textContent.trim() : '',
            sub: subNode ? subNode.textContent.trim() : '',
            keywords: item.getAttribute('data-search') || '',
            href: link ? link.getAttribute('href') : '',
            title: link ? link.getAttribute('title') : ''
        };
    });

    // فتح/إغلاق الشريط في الجوال
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            this.setAttribute('aria-expanded', sidebar.classList.contains('open'));
        });
    }

    // تصغير/تكبير الشريط
    if (sidebarMinimize) {
        sidebarMinimize.addEventListener('click', function() {
            sidebar.classList.toggle('minimized');
            const icon = this.querySelector('use');
            if (sidebar.classList.contains('minimized')) {
                icon.className = 'fa-solid fa-indent';
                this.title = 'تكبير الشريط';
                this.setAttribute('aria-label', 'تكبير الشريط الجانبي');
            } else {
                icon.className = 'fa-solid fa-outdent';
                this.title = 'تصغير الشريط';
                this.setAttribute('aria-label', 'تصغير الشريط الجانبي');
            }
        });
    }

    // الوضع الليلي
    if (darkModeToggle) {
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.setAttribute('data-theme', 'dark');
            darkModeToggle.querySelector('use');
            darkModeToggle.title = 'الوضع النهاري';
            darkModeToggle.setAttribute('aria-label', 'الوضع النهاري');
        }
        darkModeToggle.addEventListener('click', function() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('darkMode', 'disabled');
                const href = '/assets/icons/gdy-icons.svg#moon';
                const useEl = this.querySelector('use');
                if(useEl){ useEl.setAttribute('href', href); useEl.setAttribute('xlink:href', href); }
                const href = '/assets/icons/gdy-icons.svg#moon';
                this.querySelector('use').setAttribute('href', href);
                this.querySelector('use').setAttribute('xlink:href', href);
                this.title = 'الوضع الليلي';
                this.setAttribute('aria-label', 'الوضع الليلي');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('darkMode', 'enabled');
                const href = '/assets/icons/gdy-icons.svg#sun';
                const useEl = this.querySelector('use');
                if(useEl){ useEl.setAttribute('href', href); useEl.setAttribute('xlink:href', href); }
                const href = '/assets/icons/gdy-icons.svg#sun';
                this.querySelector('use').setAttribute('href', href);
                this.querySelector('use').setAttribute('xlink:href', href);
                this.title = 'الوضع النهاري';
                this.setAttribute('aria-label', 'الوضع النهاري');
            }
        });
    }

    // البحث في القوائم
    if (sidebarSearch) {
        let fuse;
        
        if (typeof Fuse !== 'undefined') {
            fuse = new Fuse(menuItems, {
                keys: [
                    { name: 'label', weight: 0.5 },
                    { name: 'keywords', weight: 0.3 },
                    { name: 'sub', weight: 0.2 }
                ],
                threshold: 0.3,
                includeScore: true
            });
        }

        sidebarSearch.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length === 0) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                return;
            }

            let results;
            
            if (fuse) {
                results = fuse.search(searchTerm).map(result => result.item);
            } else {
                const term = searchTerm.toLowerCase();
                results = menuItems.filter(item => 
                    item.label.toLowerCase().includes(term) ||
                    item.keywords.toLowerCase().includes(term) ||
                    item.sub.toLowerCase().includes(term)
                );
            }

            displaySearchResults(results, searchTerm);
        });

        function displaySearchResults(results, searchTerm) {
            searchResults.innerHTML = '';
            
            if (results.length > 0) {
                results.forEach(item => {
                    const resultItem = document.createElement('a');
                    resultItem.className = 'search-result-item';
                    resultItem.href = item.href;
                    resultItem.setAttribute('role', 'option');
                    
                    const highlightedLabel = highlightText(item.label, searchTerm);
                    const highlightedSub = highlightText(item.sub, searchTerm);
                    
                    resultItem.innerHTML = `
                        <div style="font-weight: 500;">${highlightedLabel}</div>
                        <div style="font-size: 0.8rem; opacity: 0.7;">${highlightedSub}</div>
                    `;
                    
                    resultItem.addEventListener('click', function() {
                        searchResults.style.display = 'none';
                        sidebarSearch.value = '';
                    });
                    
                    searchResults.appendChild(resultItem);
                });
            } else {
                const emptyItem = document.createElement('div');
                emptyItem.className = 'search-result-item';
                emptyItem.textContent = 'لا توجد نتائج';
                emptyItem.style.opacity = '0.7';
                emptyItem.style.fontStyle = 'italic';
                searchResults.appendChild(emptyItem);
            }
            
            searchResults.style.display = 'block';
        }

        function highlightText(text, term) {
            if (!term) return text;
            const regex = new RegExp(`(${term})`, 'gi');
            return text.replace(regex, '<mark style="background: rgba(56, 189, 248, 0.3); color: var(--sidebar-accent); padding: 0 2px; border-radius: 2px;">$1</mark>');
        }

        // التنقل باللوحة
        sidebarSearch.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const items = searchResults.querySelectorAll('.search-result-item');
                if (items.length === 0) return;
                
                let currentIndex = -1;
                items.forEach((item, index) => {
                    if (item === document.activeElement) {
                        currentIndex = index;
                    }
                });
                
                let nextIndex;
                if (e.key === 'ArrowDown') {
                    nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                } else {
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                }
                
                items[nextIndex].focus();
            }
        });

        document.addEventListener('click', function(e) {
            if (!sidebarSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchResults.style.display = 'none';
                sidebarSearch.blur();
            }
        });
    }

    // طي/فتح الأقسام
    document.querySelectorAll('.sidebar-heading').forEach(heading => {
        heading.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section');
            const sectionUl = document.getElementById(`section-${sectionId}`);
            if (!sectionUl) return;
            
            const isCollapsing = !this.classList.contains('collapsed');
            this.classList.toggle('collapsed');
            sectionUl.classList.toggle('collapsed');
            
            this.setAttribute('aria-expanded', !isCollapsing);
            sectionUl.setAttribute('aria-hidden', isCollapsing);
        });
        
        const sectionId = heading.getAttribute('data-section');
        const sectionUl = document.getElementById(`section-${sectionId}`);
        if (sectionUl) {
            const isCollapsed = heading.classList.contains('collapsed');
            heading.setAttribute('aria-expanded', !isCollapsed);
            sectionUl.setAttribute('aria-hidden', isCollapsed);
        }
    });

    // فتح القسم النشط
    const activeItem = document.querySelector('.sidebar-item-card.active');
    if (activeItem) {
        const section = activeItem.closest('.sidebar-section');
        if (section) {
            const heading = section.querySelector('.sidebar-heading');
            const list = section.querySelector('.sidebar-list');
            if (heading && list) {
                heading.classList.remove('collapsed');
                list.classList.remove('collapsed');
                heading.setAttribute('aria-expanded', 'true');
                list.setAttribute('aria-hidden', 'false');
            }
        }
        const content = document.querySelector('.sidebar-content');
        if (content) {
            setTimeout(() => {
                const rect = activeItem.getBoundingClientRect();
                const contentRect = content.getBoundingClientRect();
                content.scrollTop += (rect.top - contentRect.top) - content.clientHeight / 2 + activeItem.offsetHeight / 2;
            }, 100);
        }
    }
});

// تحسين إمكانية الوصول للشريط المصغر
document.addEventListener('keydown', function(e) {
    if (e.key === 'Tab' && document.getElementById('adminSidebar')?.classList.contains('minimized')) {
        const focused = document.activeElement;
        if (focused.closest('.sidebar')) {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.remove('minimized');
            setTimeout(() => {
                if (document.activeElement === focused) {
                    sidebar.classList.add('minimized');
                }
            }, 2000);
        }
    }
});