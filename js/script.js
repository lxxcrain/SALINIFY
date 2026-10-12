document.addEventListener("DOMContentLoaded", function () {

    // === 1. SUB-NAVIGATION TOGGLE (Learn & Play) ===
    // === 2. SUB-NAVIGATION TOGGLE ===
    function setupToggle() {
        const learnBtn = document.getElementById("learnToggle");
        const learnSubnav = document.getElementById("learnSubnav");

        if (learnBtn && learnSubnav) {
            learnBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                // Toggle the class instead of checking .style.display
                learnSubnav.classList.toggle("show-menu");

                // Toggle the active class on the parent link
                const isOpen = learnSubnav.classList.contains("show-menu");
                learnBtn.classList.toggle("active", isOpen);
            });
        }

        // Close menus if the user clicks anywhere else on the page
        document.addEventListener("click", function (e) { // Added 'e' here
            if (learnSubnav && learnSubnav.classList.contains("show-menu")) {
                // Check if click was outside both the button and the menu
                if (!learnBtn.contains(e.target) && !learnSubnav.contains(e.target)) {
                    learnSubnav.classList.remove("show-menu");
                    learnBtn.classList.remove("active");
                }
            }
        });
    }
    setupToggle();

    // === 2. NAVIGATION & PAGE TRANSITIONS ===
    const startBtn = document.getElementById("startBtn");
    const homepage = document.querySelector(".homepage");
    const classSection = document.getElementById("classes");
    const closeBtn = document.getElementById("closeBtn");
    const mainContainer = document.getElementById('mainContainer');
    const tabs = document.querySelectorAll('.tab-header');
    const tabContents = document.querySelectorAll('.tab-content');


    if (startBtn) {
        startBtn.addEventListener("click", () => {
            window.location.href = "pages/login.html";
        });
    }


    function openClassTab(dialect) {
        if (!classSection) return;
        classSection.classList.add("show");

        tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === dialect));
        tabContents.forEach(content => content.classList.toggle('active', content.id === dialect));

        if (mainContainer) {
            mainContainer.classList.remove('pampanga-active', 'ilocano-active');
            mainContainer.classList.add(`${dialect}-active`);
        }
    }

    // Sidebar Classes Link
    const navClassesLink = document.querySelector('a[href="#classes"]');
    if (navClassesLink) {
        navClassesLink.addEventListener("click", (e) => {
            e.preventDefault();
            openClassTab('pampanga');
        });
    }

    // Tab Clicking 
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const dialect = tab.dataset.tab;
            openClassTab(dialect);
        });
    });

    // Dashboard Cards
    document.querySelectorAll('.class-sub a').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const dialect = link.getAttribute('href').replace('#', '');
            openClassTab(dialect);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            classSection.classList.remove("show");
        });
    }

    // === 3. WORD OF THE DAY ===
    const dictionary = [
        { eng: "Mother", tag: "Nanay", kap: "Íma", ilo: "Inán" },
        { eng: "Father", tag: "Tatay", kap: "Tatáng", ilo: "Amá" },
        { eng: "Older sister", tag: "Àte", kap: "Àtchi", ilo: "Manáng" },
        { eng: "Older brother", tag: "Kúya", kap: "Kóya", ilo: "Manóng" },
        { eng: "Beautiful", tag: "Maganda", kap: "Malagú", ilo: "Napíntas" },
        { eng: "Handsome", tag: "Gwapo", kap: "Masantíng", ilo: "Natarakí" },
        { eng: "Hot", tag: "Mainit", kap: "Mapalí", ilo: "Napudót" },
        { eng: "Cold", tag: "Malamíg", kap: "Marimlá", ilo: "Nalamíis" },
        { eng: "Good Morning", tag: "Magandáng Umaga", kap: "Mayap á abák", ilo: "Naimbág nga bigát" },
        { eng: "Good Afternoon", tag: "Magandáng Hapon", kap: "Mayap á gatpanapún", ilo: "Naimbág nga malém" },
        { eng: "Good Evening", tag: "Magandáng Gabi", kap: "Mayap á béngi", ilo: "Naimbág nga rabí-i" },
        { eng: "Thank you very much", tag: "Maraming salamat", kap: "Dakal á Salamat", ilo: "Agyamanák Unay" },
        { eng: "Food", tag: "Pagkain", kap: "Pamangan", ilo: "Makan" },
        { eng: "Cooked Rice", tag: "Kanin", kap: "Nasi", ilo: "Inapúy" },
        { eng: "Vegetable", tag: "Gulay", kap: "Gulé", ilo: "Naténg" },
        { eng: "Fruit", tag: "Prutas", kap: "Busbus", ilo: "Bunga" },
        { eng: "Mountain", tag: "Bundok", kap: "Bunduk", ilo: "Bantáy" },
        { eng: "Ocean/Sea", tag: "Karagatan/Dagat", kap: "Dayat Malat", ilo: "Baybay" },
        { eng: "Tree", tag: "Puno", kap: "Tanaman", ilo: "Kayú" },
        { eng: "River", tag: "Ilog", kap: "Ilug", ilo: "Karayan" }
    ];

    const titleElement = document.getElementById('wotd-title');
    const defElement = document.getElementById('wotd-definition');

    if (titleElement && defElement) {
        const now = new Date();
        const start = new Date(now.getFullYear(), 0, 0);
        const diff = now - start;
        const oneDay = 1000 * 60 * 60 * 24;
        const dayOfYear = Math.floor(diff / oneDay);
        const word = dictionary[dayOfYear % dictionary.length];

        titleElement.innerText = word.eng;
        defElement.innerHTML = `
            <strong>Tagalog:</strong> ${word.tag} |
            <strong>Kapampangan:</strong> ${word.kap} |
            <strong>Ilokano:</strong> ${word.ilo}
        `;
    }

    // === 4. HIGHLIGHT ACTIVE NAVIGATION ===
    const navLinks = document.querySelectorAll('nav a'); // Adjust selector to match your nav links
    const homepageLink = document.querySelector('a[href="#home"]'); // Update if your homepage link has a different href

    function updateActiveNav() {
        const homepageVisible = document.querySelector('.homepage.show') !== null;
        const classesVisible = document.querySelector('#classes.show') !== null;

        navLinks.forEach(link => link.classList.remove('active'));

        if (homepageVisible && homepageLink) {
            homepageLink.classList.add('active');
        } else if (classesVisible) {
            const activeTab = document.querySelector('.tab-header.active');
            if (activeTab) {
                const targetLink = document.querySelector(`a[href="#${activeTab.dataset.tab}"]`);
                if (targetLink) targetLink.classList.add('active');
            }
        }
    }

    // Call it on page load
    updateActiveNav();

    // Update nav whenever a tab or page changes
    tabs.forEach(tab => tab.addEventListener('click', updateActiveNav));
    if (navClassesLink) navClassesLink.addEventListener('click', updateActiveNav);
    if (startBtn) startBtn.addEventListener('click', updateActiveNav);
    if (closeBtn) closeBtn.addEventListener('click', updateActiveNav);

    //ACCOUNT POP-UP

    const accountBtn = document.getElementById('accountBtn');
    const accountModal = document.getElementById('accountModal');
    const closeAccountBtn = document.getElementById('closeAccountBtn');
    const accountDoneBtn = document.getElementById('accountDoneBtn');

    const accountUsername = document.getElementById('accountUsername');
    const accountEmail = document.getElementById('accountEmail');
    const accountCreated = document.getElementById('accountCreated');
    const accountMessage = document.getElementById('accountMessage');

    function closeAccountModal() {
        accountModal.classList.add('hidden');
    }
    if (accountBtn && accountModal) {
        accountBtn.addEventListener('click', async () => {
            accountModal.classList.remove('hidden');

            accountUsername.textContent = 'Loading...';
            accountEmail.textContent = 'Loading...';
            accountCreated.textContent = 'Loading...';
            accountMessage.textContent = '';

            try {
                const response = await fetch('../api/account.php', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || 'Unable to load account details.'
                    );
                }

                accountUsername.textContent = data.username;
                accountEmail.textContent = data.email;

                if (data.created_at) {
                    const date = new Date(data.created_at);

                    accountCreated.textContent =
                        Number.isNaN(date.getTime())
                            ? data.created_at
                            : date.toLocaleDateString('en-PH', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                } else {
                    accountCreated.textContent = 'Not available';
                }

            } catch (error) {
                console.error('Account loading error:', error);

                accountUsername.textContent = '—';
                accountEmail.textContent = '—';
                accountCreated.textContent = '—';

                accountMessage.textContent =
                    error.message || 'Unable to load account details.';
            }
        });

        if (closeAccountBtn) {
            closeAccountBtn.addEventListener('click', closeAccountModal);
        }

        if (accountDoneBtn) {
            accountDoneBtn.addEventListener('click', closeAccountModal);
        }

        accountModal.addEventListener('click', (event) => {
            if (event.target === accountModal) {
                closeAccountModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (
                event.key === 'Escape' &&
                !accountModal.classList.contains('hidden')
            ) {
                closeAccountModal();
            }
        });
    }
});

// 1. Move scrollTab OUTSIDE DOMContentLoaded so the HTML buttons can find it
function scrollTab(tabId, direction) {
    const wrapper = document.getElementById(tabId + '-wrapper');
    const scrollAmount = 330;
    wrapper.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
    });
}

document.addEventListener("DOMContentLoaded", function () {
    const mainContent = document.querySelector('.main-content');
    const tabs = document.querySelectorAll('.tab-header');
    const tabContents = document.querySelectorAll('.tab-content');
    const mainContainer = document.getElementById('mainContainer');

    // --- ENTRANCE ANIMATION ---
    setTimeout(() => {
        mainContent.classList.add('show');
    }, 100);

    // --- NAVIGATION & EXIT LOGIC ---
    function slideDownAndRedirect(url) {
        mainContent.classList.remove('show');
        mainContent.classList.add('exit');
        setTimeout(() => {
            window.location.href = url;
        }, 600);
    }

    // Close Button
    const closeButton = document.getElementById('closeBtn');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            slideDownAndRedirect("home.html");
        });
    }

    // Sidebar Links
    const navLinks = document.querySelectorAll('.sidebar ul li a');
    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const destination = this.getAttribute('href');
            slideDownAndRedirect(destination);
        });
    });

    // --- TAB SWITCHING LOGIC ---
    function switchTab(tabId) {
        // Remove active classes
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));

        // Activate selected tab header
        const activeHeader = document.querySelector(`[data-tab="${tabId}"]`);
        if (activeHeader) activeHeader.classList.add('active');

        // Activate selected content
        const activeContent = document.getElementById(tabId);
        if (activeContent) activeContent.classList.add('active');

        // Update container theme
        if (mainContainer) {
            mainContainer.classList.remove('pampanga-active', 'ilocos-active');
            mainContainer.classList.add(`${tabId}-active`);
        }
    }

    // Add Click listeners to tabs
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            switchTab(tab.dataset.tab);
        });
    });

    // --- HASH/URL CHECK ---
    const currentHash = window.location.hash.substring(1);
    if (currentHash === "ilocos" || currentHash === "pampanga") {
        switchTab(currentHash);
    }
});

// === POP-UP LOGIC ===
// === 5. POP-UP MODAL LOGIC ===
const modal = document.getElementById("dashboardModal");
const dashboard = document.querySelector(".dashboard");
const closeModal = document.getElementById("closeModal");


if (dashboard && modal) {
    dashboard.addEventListener("click", (e) => {
        // Prevent opening modal if clicking direct links
        if (e.target.tagName === 'A' || e.target.parentElement.tagName === 'A') return;
        modal.classList.add("show-modal");
        switchTab('kap');
    });
}


if (closeModal) {
    closeModal.addEventListener("click", () => {
        // 1. Start the Slide Down animation
        modal.classList.add("modal-closing");


        // 2. Wait 300ms for the animation to finish, then hide it
        setTimeout(() => {
            modal.classList.remove("show-modal");
            modal.classList.remove("modal-closing");
        }, 300);
    });
}


// Close on outside click
window.addEventListener("click", (e) => {
    if (e.target === modal) {
        modal.classList.add("modal-closing");
        setTimeout(() => {
            modal.classList.remove("show-modal");
            modal.classList.remove("modal-closing");
        }, 300);
    }
});


function switchTab(dialect) {
    // Update content visibility
    document.querySelectorAll('.dialect-tab').forEach(tab => tab.classList.remove('active'));
    const targetTab = document.getElementById('tab-' + dialect);
    if (targetTab) targetTab.classList.add('active');


    // Update footer button styling
    document.querySelectorAll('.nav-dot-btn').forEach(btn => btn.classList.remove('active'));
    const targetBtn = document.querySelector('.' + dialect + '-btn');
    if (targetBtn) targetBtn.classList.add('active');


    // Update the Mac-style window title
    const title = document.getElementById('modalDynamicTitle');
    if (title) {
        title.innerText = (dialect === 'kap') ? 'Kapampangan Dialect' : 'Ilocano Dialect';
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Select the links inside the class cards
    const classLinks = document.querySelectorAll('.class-sub a');

    classLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent instant jump
            const destination = this.getAttribute('href');

            // Trigger your homepage exit animation (if you have one)
            const homepage = document.querySelector('.homepage');
            homepage.classList.add('exit-fade'); // Example animation class

            // Redirect after a short delay for the animation
            setTimeout(() => {
                window.location.href = destination;
            }, 400);
        });
    });

    // --- GET STARTED BUTTON LOGIC ---
    const startBtn = document.getElementById('startBtn');
    const welcomePage = document.querySelector('.welcome-page');
    const homepage = document.querySelector('.homepage');
});
