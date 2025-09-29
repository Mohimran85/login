// Student Dashboard JavaScript - Mobile Responsive Functions

class StudentDashboard {
  constructor() {
    this.sidebar = document.getElementById("sidebar");
    this.init();
  }

  init() {
    this.setupEventListeners();
    this.handleResponsiveLayout();
    this.animateCards();
  }

  setupEventListeners() {
    // Mobile menu toggle
    const menuIcon = document.querySelector(".menu-icon");
    if (menuIcon) {
      menuIcon.addEventListener("click", () => this.toggleSidebar());
    }

    // Sidebar close button
    const closeBtn = document.querySelector(
      ".sidebar-band .material-symbols-outlined"
    );
    if (closeBtn) {
      closeBtn.addEventListener("click", () => this.closeSidebar());
    }

    // Click outside to close sidebar on mobile
    document.addEventListener("click", (e) => this.handleOutsideClick(e));

    // Window resize handler
    window.addEventListener("resize", () => this.handleResize());

    // Card hover animations
    this.setupCardAnimations();
  }

  toggleSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.toggle("active");
    }
  }

  openSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.add("active");
    }
  }

  closeSidebar() {
    if (this.sidebar) {
      this.sidebar.classList.remove("active");
    }
  }

  handleOutsideClick(event) {
    const isMobile = window.innerWidth <= 768;
    const isClickOutside = this.sidebar && !this.sidebar.contains(event.target);
    const isMenuIcon = event.target.closest(".menu-icon");

    if (
      isMobile &&
      isClickOutside &&
      !isMenuIcon &&
      this.sidebar.classList.contains("active")
    ) {
      this.closeSidebar();
    }
  }

  handleResize() {
    const isMobile = window.innerWidth <= 768;

    if (!isMobile && this.sidebar) {
      this.sidebar.classList.remove("active");
    }

    // Recalculate card animations on resize
    this.animateCards();
  }

  handleResponsiveLayout() {
    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
      document.body.classList.add("mobile-layout");
    } else {
      document.body.classList.remove("mobile-layout");
    }
  }

  setupCardAnimations() {
    const cards = document.querySelectorAll(".card");

    cards.forEach((card, index) => {
      card.addEventListener("mouseenter", () => {
        card.style.transform = "translateY(-8px) scale(1.02)";
      });

      card.addEventListener("mouseleave", () => {
        card.style.transform = "translateY(0) scale(1)";
      });
    });
  }

  animateCards() {
    const cards = document.querySelectorAll(".card");
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.classList.add("fade-in");
            }, index * 100);
          }
        });
      },
      { threshold: 0.1 }
    );

    cards.forEach((card) => {
      observer.observe(card);
    });
  }

  // Utility function to update card data dynamically
  updateCardData(cardSelector, newValue) {
    const card = document.querySelector(cardSelector);
    if (card) {
      const valueElement = card.querySelector("h1");
      if (valueElement) {
        // Animate number change
        this.animateNumber(
          valueElement,
          parseInt(valueElement.textContent) || 0,
          newValue
        );
      }
    }
  }

  animateNumber(element, start, end, duration = 1000) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
      current += increment;
      if (
        (increment > 0 && current >= end) ||
        (increment < 0 && current <= end)
      ) {
        current = end;
        clearInterval(timer);
      }
      element.textContent = Math.floor(current);
    }, 16);
  }

  // Show loading state for cards
  showCardLoading(cardSelector) {
    const card = document.querySelector(cardSelector);
    if (card) {
      card.classList.add("loading");
    }
  }

  hideCardLoading(cardSelector) {
    const card = document.querySelector(cardSelector);
    if (card) {
      card.classList.remove("loading");
    }
  }

  // Show toast notifications
  showToast(message, type = "info", duration = 3000) {
    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
            <div class="toast-content">
                <span class="toast-message">${message}</span>
                <button class="toast-close">&times;</button>
            </div>
        `;

    // Add toast styles if not already present
    if (!document.querySelector("#toast-styles")) {
      const styles = document.createElement("style");
      styles.id = "toast-styles";
      styles.textContent = `
                .toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 8px;
                    color: white;
                    z-index: 1000;
                    transform: translateX(400px);
                    transition: transform 0.3s ease;
                    max-width: 300px;
                }
                .toast.toast-info { background: #17a2b8; }
                .toast.toast-success { background: #28a745; }
                .toast.toast-warning { background: #ffc107; color: #212529; }
                .toast.toast-error { background: #dc3545; }
                .toast.show { transform: translateX(0); }
                .toast-content { display: flex; justify-content: space-between; align-items: center; }
                .toast-close { background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; margin-left: 10px; }
            `;
      document.head.appendChild(styles);
    }

    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.add("show"), 100);

    // Auto remove
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => document.body.removeChild(toast), 300);
    }, duration);

    // Close button
    toast.querySelector(".toast-close").addEventListener("click", () => {
      toast.classList.remove("show");
      setTimeout(() => document.body.removeChild(toast), 300);
    });
  }
}

// Global functions for backward compatibility
function openSidebar() {
  if (window.dashboardInstance) {
    window.dashboardInstance.openSidebar();
  }
}

function closeSidebar() {
  if (window.dashboardInstance) {
    window.dashboardInstance.closeSidebar();
  }
}

// Initialize dashboard when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  window.dashboardInstance = new StudentDashboard();

  // Add smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });

  // Add loading states for async operations
  const asyncLinks = document.querySelectorAll('a[href$=".php"]');
  asyncLinks.forEach((link) => {
    link.addEventListener("click", function () {
      const card = this.closest(".card");
      if (card && window.dashboardInstance) {
        window.dashboardInstance.showCardLoading(".card");
      }
    });
  });
});

// Export for ES6 modules if needed
if (typeof module !== "undefined" && module.exports) {
  module.exports = StudentDashboard;
}
