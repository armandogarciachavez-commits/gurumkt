
// Initialize AOS (Animate On Scroll)
if (typeof AOS !== 'undefined') {
    AOS.init({
        once: true,
        mirror: false,
        offset: 100,
    });
}

// Fondo animado: particles.js reemplazado por CSS puro (ver styles.css #particles-js)


// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
}

// Close Modal on ESC key — payment.js se carga dinámicamente bajo demanda
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (typeof closeModal === 'function') closeModal();
        if (typeof closeServiceModal === 'function') closeServiceModal();
        if (typeof closePaymentModal === 'function') closePaymentModal();
    }
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.getElementById('navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('shadow-lg');
        } else {
            navbar.classList.remove('shadow-lg');
        }
    }
});

// Portfolio Filtering
const filterBtns = document.querySelectorAll('.filter-btn');
const portfolioItems = document.querySelectorAll('.portfolio-item');

if (filterBtns.length > 0) {
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => {
                b.classList.remove('bg-white', 'text-black');
                b.classList.add('border-white/10', 'hover:bg-white/10');
            });
            btn.classList.remove('border-white/10', 'hover:bg-white/10');
            btn.classList.add('bg-white', 'text-black');

            const filterValue = btn.getAttribute('data-filter');
            portfolioItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                const hideOnAll = item.getAttribute('data-hide-on-all') === 'true';

                if (filterValue === 'all') {
                    if (hideOnAll) item.classList.add('hidden');
                    else item.classList.remove('hidden');
                } else {
                    if (itemCategory === filterValue) item.classList.remove('hidden');
                    else item.classList.add('hidden');
                }
            });
        });
    });
}

// Video Modal Logic
const videoModal = document.getElementById('videoModal');
const videoFrame = document.getElementById('videoFrame');

function openModal(videoUrl) {
    if (videoModal && videoFrame && videoUrl) {
        const separator = videoUrl.includes('?') ? '&' : '?';
        videoFrame.src = `${videoUrl}${separator}autoplay=1&mute=1&rel=0`;
        videoModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal() {
    if (videoModal && videoFrame) {
        videoModal.classList.add('hidden');
        videoFrame.src = "";
        document.body.style.overflow = 'auto';
    }
}

// Service Modal Logic
const serviceModal = document.getElementById('serviceModal');
const serviceTitle = document.getElementById('serviceTitle');
const serviceList = document.getElementById('serviceList');
const servicePrice = document.getElementById('servicePrice');
const serviceIcon = document.getElementById('serviceIcon');

const servicesData = {
    marketing: {
        title: "Manejo de Redes",
        icon: "fa-bullhorn",
        iconColor: "text-blue-400",
        items: ["Manejo de Redes", "Creación de cuentas", "Contenido", "Segmentación", "Campañas"],
        price: "Paquete inicial $3,500"
    },
    branding: {
        title: "Branding & Diseño",
        icon: "fa-pen-nib",
        iconColor: "text-purple-400",
        items: ["Imagen gráfica", "Manual de identidad", "Aplicaciones gráficas"],
        price: "Costo inicial $5,000"
    }
};

function openServiceModal(serviceId) {
    const data = servicesData[serviceId];
    if (data && serviceModal) {
        serviceTitle.innerText = data.title;
        servicePrice.innerText = data.price;
        serviceIcon.className = `fas ${data.icon} text-4xl ${data.iconColor}`;
        serviceList.innerHTML = data.items.map(item =>
            `<li class="flex items-center"><i class="fas fa-check text-blue-500 mr-3"></i>${item}</li>`
        ).join('');
        serviceModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeServiceModal() {
    if (serviceModal) {
        serviceModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

if (serviceModal) {
    serviceModal.addEventListener('click', (e) => {
        if (e.target === serviceModal) closeServiceModal();
    });
}


// Auto-trigger active filter on load
document.addEventListener('DOMContentLoaded', () => {
    const activeFilter = document.querySelector('.filter-btn.active');
    if (activeFilter) {
        activeFilter.click();
    }
});

