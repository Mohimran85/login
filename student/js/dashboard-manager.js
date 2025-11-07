/**
 * Advanced Dashboard Manager
 * Handles AJAX loading and dynamic updates
 */
class DashboardManager {
  constructor() {
    this.cache = new Map();
    this.cacheExpiry = 5 * 60 * 1000; // 5 minutes
    this.loadingElements = new Set();
    this.init();
  }

  init() {
    // Load dashboard components asynchronously
    this.loadDashboardComponents();

    // Set up auto-refresh
    this.setupAutoRefresh();

    // Set up event listeners
    this.setupEventListeners();
  }

  /**
   * Load all dashboard components
   */
  async loadDashboardComponents() {
    try {
      // Load critical stats first
      await this.loadStats();

      // Load secondary components with delay
      setTimeout(() => this.loadRecentActivities(), 100);
      setTimeout(() => this.loadEventBreakdown(), 200);
      setTimeout(() => this.loadODRequests(), 300);
    } catch (error) {
      console.error("Dashboard loading error:", error);
      this.showError("Failed to load dashboard components");
    }
  }

  /**
   * Load dashboard statistics
   */
  async loadStats() {
    const cacheKey = "dashboard_stats";
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.updateStatsUI(cached);
      return;
    }

    this.showLoading("stats");

    try {
      const response = await fetch("ajax/dashboard.php?action=dashboard_stats");
      const result = await response.json();

      if (result.success) {
        this.setCache(cacheKey, result.data);
        this.updateStatsUI(result.data);
      } else {
        throw new Error(result.message || "Failed to load stats");
      }
    } catch (error) {
      console.error("Stats loading error:", error);
      this.showError("Failed to load statistics", "stats");
    } finally {
      this.hideLoading("stats");
    }
  }

  /**
   * Load recent activities
   */
  async loadRecentActivities() {
    const cacheKey = "recent_activities";
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.updateActivitiesUI(cached);
      return;
    }

    this.showLoading("activities");

    try {
      const response = await fetch(
        "ajax/dashboard.php?action=recent_activities"
      );
      const result = await response.json();

      if (result.success) {
        this.setCache(cacheKey, result.data);
        this.updateActivitiesUI(result.data);
      }
    } catch (error) {
      console.error("Activities loading error:", error);
      this.showError("Failed to load recent activities", "activities");
    } finally {
      this.hideLoading("activities");
    }
  }

  /**
   * Load event breakdown
   */
  async loadEventBreakdown() {
    const cacheKey = "event_breakdown";
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.updateBreakdownUI(cached);
      return;
    }

    this.showLoading("breakdown");

    try {
      const response = await fetch("ajax/dashboard.php?action=event_breakdown");
      const result = await response.json();

      if (result.success) {
        this.setCache(cacheKey, result.data);
        this.updateBreakdownUI(result.data);
      }
    } catch (error) {
      console.error("Breakdown loading error:", error);
      this.showError("Failed to load event breakdown", "breakdown");
    } finally {
      this.hideLoading("breakdown");
    }
  }

  /**
   * Load OD requests
   */
  async loadODRequests() {
    const cacheKey = "od_requests";
    const cached = this.getFromCache(cacheKey);

    if (cached) {
      this.updateODRequestsUI(cached);
      return;
    }

    this.showLoading("od-requests");

    try {
      const response = await fetch("ajax/dashboard.php?action=od_requests");
      const result = await response.json();

      if (result.success) {
        this.setCache(cacheKey, result.data);
        this.updateODRequestsUI(result.data);
      }
    } catch (error) {
      console.error("OD requests loading error:", error);
      this.showError("Failed to load OD requests", "od-requests");
    } finally {
      this.hideLoading("od-requests");
    }
  }

  /**
   * Update statistics UI
   */
  updateStatsUI(data) {
    // Update main statistics cards
    const statsElements = {
      ".total-events": data.total_events,
      ".events-won": data.events_won,
      ".success-rate": data.success_rate + "%",
      ".od-total": data.od_stats.total,
      ".od-pending": data.od_stats.pending,
      ".od-approved": data.od_stats.approved,
    };

    Object.entries(statsElements).forEach(([selector, value]) => {
      const element = document.querySelector(selector);
      if (element) {
        this.animateValue(element, value);
      }
    });
  }

  /**
   * Update activities UI
   */
  updateActivitiesUI(activities) {
    const container = document.querySelector(".activities-list");
    if (!container) return;

    if (activities.length === 0) {
      container.innerHTML = this.getEmptyState(
        "No recent activities",
        "event_busy"
      );
      return;
    }

    const html = activities
      .map(
        (activity) => `
            <div class="activity-item" data-aos="fade-up">
                <div class="activity-icon">
                    <span class="material-symbols-outlined">event</span>
                </div>
                <div class="activity-details">
                    <h4>${this.escapeHtml(activity.event_name)}</h4>
                    <p class="activity-meta">
                        <span class="event-type">${this.escapeHtml(
                          activity.event_type
                        )}</span>
                        <span class="event-date">${
                          activity.formatted_date
                        }</span>
                        ${
                          activity.has_prize
                            ? `<span class="prize-badge">🏆${this.escapeHtml(
                                activity.prize
                              )}</span>`
                            : ""
                        }
                    </p>
                </div>
            </div>
        `
      )
      .join("");

    container.innerHTML = html;
  }

  /**
   * Update breakdown UI
   */
  updateBreakdownUI(breakdown) {
    const container = document.querySelector(".categories-list");
    if (!container) return;

    if (breakdown.length === 0) {
      container.innerHTML = this.getEmptyState(
        "No event categories yet",
        "category"
      );
      return;
    }

    const html = breakdown
      .map(
        (type) => `
            <div class="category-item" data-aos="fade-left">
                <div class="category-info">
                    <span class="category-name">${this.escapeHtml(
                      type.event_type
                    )}</span>
                    <div class="category-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${
                              type.percentage
                            }%"></div>
                        </div>
                    </div>
                </div>
                <span class="category-count">${type.count}</span>
            </div>
        `
      )
      .join("");

    container.innerHTML = html;
  }

  /**
   * Update OD requests UI
   */
  updateODRequestsUI(requests) {
    const container = document.querySelector(".od-requests-list");
    if (!container) return;

    if (requests.length === 0) {
      container.innerHTML = this.getEmptyState(
        "No OD requests yet",
        "description"
      );
      return;
    }

    const html = requests
      .map(
        (request) => `
            <div class="od-request-item" data-aos="fade-right">
                <div class="od-request-icon">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="od-request-details">
                    <h4>${this.escapeHtml(request.event_name)}</h4>
                    <p class="od-request-meta">
                        <span class="od-status ${request.status_class}">
                            ${
                              request.status.charAt(0).toUpperCase() +
                              request.status.slice(1)
                            }
                        </span>
                        <span class="od-date">${
                          request.formatted_event_date
                        }</span>
                    </p>
                </div>
            </div>
        `
      )
      .join("");

    container.innerHTML = html;
  }

  /**
   * Show loading state
   */
  showLoading(section) {
    this.loadingElements.add(section);
    const container = document.querySelector(`[data-section="${section}"]`);
    if (container) {
      container.classList.add("loading");
      const loadingHtml = `
                <div class="loading-skeleton">
                    <div class="skeleton-item"></div>
                    <div class="skeleton-item"></div>
                    <div class="skeleton-item"></div>
                </div>
            `;
      const loadingElement = document.createElement("div");
      loadingElement.className = "loading-overlay";
      loadingElement.innerHTML = loadingHtml;
      container.appendChild(loadingElement);
    }
  }

  /**
   * Hide loading state
   */
  hideLoading(section) {
    this.loadingElements.delete(section);
    const container = document.querySelector(`[data-section="${section}"]`);
    if (container) {
      container.classList.remove("loading");
      const loadingOverlay = container.querySelector(".loading-overlay");
      if (loadingOverlay) {
        loadingOverlay.remove();
      }
    }
  }

  /**
   * Show error message
   */
  showError(message, section = null) {
    if (section) {
      const container = document.querySelector(`[data-section="${section}"]`);
      if (container) {
        container.innerHTML = `
                    <div class="error-state">
                        <span class="material-symbols-outlined">error</span>
                        <p>${message}</p>
                        <button onclick="dashboardManager.retry('${section}')" class="retry-btn">
                            Retry
                        </button>
                    </div>
                `;
      }
    } else {
      // Show global error notification
      this.showNotification(message, "error");
    }
  }

  /**
   * Retry loading a section
   */
  async retry(section) {
    this.clearCache();

    switch (section) {
      case "stats":
        await this.loadStats();
        break;
      case "activities":
        await this.loadRecentActivities();
        break;
      case "breakdown":
        await this.loadEventBreakdown();
        break;
      case "od-requests":
        await this.loadODRequests();
        break;
    }
  }

  /**
   * Set up auto-refresh
   */
  setupAutoRefresh() {
    // Refresh every 5 minutes
    setInterval(() => {
      if (!document.hidden) {
        this.clearCache();
        this.loadDashboardComponents();
      }
    }, 5 * 60 * 1000);

    // Refresh when tab becomes visible
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) {
        const lastRefresh = localStorage.getItem("lastDashboardRefresh");
        const now = Date.now();

        if (!lastRefresh || now - parseInt(lastRefresh) > 2 * 60 * 1000) {
          this.clearCache();
          this.loadDashboardComponents();
          localStorage.setItem("lastDashboardRefresh", now.toString());
        }
      }
    });
  }

  /**
   * Set up event listeners
   */
  setupEventListeners() {
    // Manual refresh button
    const refreshBtn = document.querySelector(".refresh-dashboard");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", () => {
        this.clearCache();
        this.loadDashboardComponents();
        this.showNotification("Dashboard refreshed", "success");
      });
    }
  }

  /**
   * Cache management
   */
  getFromCache(key) {
    const cached = this.cache.get(key);
    if (cached && Date.now() - cached.timestamp < this.cacheExpiry) {
      return cached.data;
    }
    return null;
  }

  setCache(key, data) {
    this.cache.set(key, {
      data: data,
      timestamp: Date.now(),
    });
  }

  clearCache() {
    this.cache.clear();
  }

  /**
   * Utility functions
   */
  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  animateValue(element, newValue) {
    const currentValue = element.textContent;
    if (currentValue !== newValue.toString()) {
      element.style.transition = "transform 0.3s ease";
      element.style.transform = "scale(1.1)";
      element.textContent = newValue;

      setTimeout(() => {
        element.style.transform = "scale(1)";
      }, 300);
    }
  }

  getEmptyState(message, icon) {
    return `
            <div class="empty-state">
                <span class="material-symbols-outlined">${icon}</span>
                <p>${message}</p>
            </div>
        `;
  }

  showNotification(message, type = "info") {
    // Simple notification system
    const notification = document.createElement("div");
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: ${
              type === "error"
                ? "#f44336"
                : type === "success"
                ? "#4caf50"
                : "#2196f3"
            };
            color: white;
            border-radius: 8px;
            z-index: 10000;
            transition: all 0.3s ease;
        `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.opacity = "0";
      notification.style.transform = "translateX(100%)";
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }
}

// Initialize dashboard manager when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.dashboardManager = new DashboardManager();
});

// Add loading skeleton CSS
const skeletonCSS = `
    .loading-skeleton {
        animation: pulse 1.5s ease-in-out infinite;
    }
    
    .skeleton-item {
        height: 20px;
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 2s infinite;
        border-radius: 4px;
        margin-bottom: 8px;
    }
    
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
    
    .error-state {
        text-align: center;
        padding: 20px;
        color: #666;
    }
    
    .error-state .material-symbols-outlined {
        font-size: 48px;
        color: #f44336;
        margin-bottom: 10px;
    }
    
    .retry-btn {
        background: #2196f3;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 10px;
    }
    
    .retry-btn:hover {
        background: #1976d2;
    }
`;

// Inject skeleton CSS
const style = document.createElement("style");
style.textContent = skeletonCSS;
document.head.appendChild(style);
