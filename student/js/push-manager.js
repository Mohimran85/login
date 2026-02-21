/**
 * Push Notification Manager
 * Handles Web Push notifications for student dashboard
 * Compatible with Median.co web-to-app conversion
 */

class PushNotificationManager {
  constructor() {
    this.serviceWorkerRegistration = null;
    this.pushSubscription = null;
    this.publicKey = null;
    this.isSupported = false;
    this.permissionStatus = "default";

    this.init();
  }

  /**
   * Initialize push notification system
   */
  async init() {
    // Check if notifications are supported
    if (!("Notification" in window)) {
      console.warn("[Push] Notifications not supported");
      return;
    }

    if (!("serviceWorker" in navigator)) {
      console.warn("[Push] Service Worker not supported");
      return;
    }

    if (!("PushManager" in window)) {
      console.warn("[Push] Push messaging not supported");
      return;
    }

    this.isSupported = true;
    this.permissionStatus = Notification.permission;
    console.log(
      "[Push] Push notifications supported. Permission:",
      this.permissionStatus,
    );

    // Register service worker
    try {
      this.serviceWorkerRegistration = await navigator.serviceWorker.register(
        "/sw.js",
        {
          scope: "/",
        },
      );
      console.log(
        "[Push] Service Worker registered:",
        this.serviceWorkerRegistration.scope,
      );

      // Wait for service worker to be ready
      await navigator.serviceWorker.ready;

      // Get existing subscription
      await this.checkSubscription();

      // If permission granted but not subscribed, subscribe
      if (this.permissionStatus === "granted" && !this.pushSubscription) {
        await this.subscribe();
      }
    } catch (error) {
      console.error("[Push] Service Worker registration failed:", error);
    }
  }

  /**
   * Check if user already has an active subscription
   */
  async checkSubscription() {
    if (!this.serviceWorkerRegistration) {
      console.warn("[Push] Service Worker not registered");
      return false;
    }

    try {
      this.pushSubscription =
        await this.serviceWorkerRegistration.pushManager.getSubscription();

      if (this.pushSubscription) {
        console.log("[Push] Active subscription found");

        // Verify subscription on server
        const status = await this.getSubscriptionStatus();
        if (!status.subscribed) {
          console.log(
            "[Push] Local subscription not on server, re-subscribing...",
          );
          await this.sendSubscriptionToServer(this.pushSubscription);
        }

        return true;
      }

      console.log("[Push] No active subscription");
      return false;
    } catch (error) {
      console.error("[Push] Error checking subscription:", error);
      return false;
    }
  }

  /**
   * Request notification permission and subscribe
   */
  async requestPermission() {
    if (!this.isSupported) {
      alert("Push notifications are not supported in your browser.");
      return false;
    }

    if (this.permissionStatus === "granted") {
      console.log("[Push] Permission already granted");
      return true;
    }

    try {
      const permission = await Notification.requestPermission();
      this.permissionStatus = permission;
      console.log("[Push] Permission result:", permission);

      if (permission === "granted") {
        await this.subscribe();
        return true;
      } else if (permission === "denied") {
        alert(
          "You have blocked notifications. To enable them, please update your browser settings.",
        );
        return false;
      }

      return false;
    } catch (error) {
      console.error("[Push] Error requesting permission:", error);
      alert("Failed to request notification permission: " + error.message);
      return false;
    }
  }

  /**
   * Subscribe to push notifications
   */
  async subscribe() {
    if (!this.serviceWorkerRegistration) {
      console.error("[Push] Service Worker not registered");
      return false;
    }

    if (this.permissionStatus !== "granted") {
      console.error(
        "[Push] Permission not granted. Current status:",
        this.permissionStatus,
      );
      return false;
    }

    try {
      // Get VAPID public key from server
      if (!this.publicKey) {
        const response = await fetch(
          "/student/ajax/push_subscription.php?action=get_public_key",
        );
        const data = await response.json();

        if (!data.success) {
          throw new Error(data.error || "Failed to get public key");
        }

        this.publicKey = data.publicKey;
        console.log("[Push] Got VAPID public key");
      }

      // Subscribe to push manager
      const applicationServerKey = this.urlBase64ToUint8Array(this.publicKey);

      this.pushSubscription =
        await this.serviceWorkerRegistration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: applicationServerKey,
        });

      console.log("[Push] Subscribed to push notifications");

      // Send subscription to server
      const result = await this.sendSubscriptionToServer(this.pushSubscription);

      if (result) {
        this.showNotification(
          "Notifications Enabled!",
          "You will now receive hackathon updates.",
        );
        return true;
      }

      return false;
    } catch (error) {
      console.error("[Push] Subscription failed:", error);

      if (error.name === "NotAllowedError") {
        alert(
          "Notification permission was denied. Please enable it in your browser settings.",
        );
      } else {
        alert("Failed to subscribe to notifications: " + error.message);
      }

      return false;
    }
  }

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribe() {
    if (!this.pushSubscription) {
      console.log("[Push] No active subscription to unsubscribe");
      return true;
    }

    try {
      // Get endpoint before unsubscribing
      const endpoint = this.pushSubscription.endpoint;

      // Unsubscribe from push manager
      const success = await this.pushSubscription.unsubscribe();

      if (success) {
        console.log("[Push] Unsubscribed from push notifications");

        // Notify server
        await this.sendUnsubscribeToServer(endpoint);

        this.pushSubscription = null;
        this.showNotification(
          "Notifications Disabled",
          "You will no longer receive updates.",
        );
        return true;
      }

      return false;
    } catch (error) {
      console.error("[Push] Unsubscribe failed:", error);
      return false;
    }
  }

  /**
   * Send subscription to server
   */
  async sendSubscriptionToServer(subscription) {
    try {
      const subscriptionJSON = subscription.toJSON();

      const response = await fetch(
        "/student/ajax/push_subscription.php?action=subscribe",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            subscription: subscriptionJSON,
          }),
        },
      );

      const data = await response.json();

      if (data.success) {
        console.log("[Push] Subscription saved to server");
        return true;
      } else {
        console.error("[Push] Server rejected subscription:", data.error);
        return false;
      }
    } catch (error) {
      console.error("[Push] Failed to send subscription to server:", error);
      return false;
    }
  }

  /**
   * Send unsubscribe notification to server
   */
  async sendUnsubscribeToServer(endpoint) {
    try {
      const response = await fetch(
        "/student/ajax/push_subscription.php?action=unsubscribe",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            endpoint: endpoint,
          }),
        },
      );

      const data = await response.json();

      if (data.success) {
        console.log("[Push] Unsubscribe confirmed by server");
        return true;
      }

      return false;
    } catch (error) {
      console.error("[Push] Failed to send unsubscribe to server:", error);
      return false;
    }
  }

  /**
   * Get subscription status from server
   */
  async getSubscriptionStatus() {
    try {
      const response = await fetch(
        "/student/ajax/push_subscription.php?action=status",
      );
      const data = await response.json();

      if (data.success) {
        return {
          subscribed: data.subscribed,
          count: data.subscription_count,
        };
      }

      return { subscribed: false, count: 0 };
    } catch (error) {
      console.error("[Push] Failed to get subscription status:", error);
      return { subscribed: false, count: 0 };
    }
  }

  /**
   * Send test notification
   */
  async sendTest() {
    try {
      const response = await fetch(
        "/student/ajax/push_subscription.php?action=test",
      );
      const data = await response.json();

      if (data.success) {
        console.log("[Push] Test notification sent:", data.stats);
        alert("Test notification sent! Check your notifications.");
        return true;
      } else {
        alert("Failed to send test notification: " + data.error);
        return false;
      }
    } catch (error) {
      console.error("[Push] Failed to send test:", error);
      alert("Failed to send test notification: " + error.message);
      return false;
    }
  }

  /**
   * Show local notification (for testing)
   */
  async showNotification(title, body, options = {}) {
    if (!this.serviceWorkerRegistration) {
      console.warn("[Push] Service Worker not registered");
      return;
    }

    if (this.permissionStatus !== "granted") {
      console.warn("[Push] Permission not granted");
      return;
    }

    try {
      await this.serviceWorkerRegistration.showNotification(title, {
        body: body,
        icon: "/asserts/images/logo.png",
        badge: "/asserts/images/badge.png",
        vibrate: [200, 100, 200],
        tag: options.tag || "notification-" + Date.now(),
        requireInteraction: false,
        ...options,
      });
    } catch (error) {
      console.error("[Push] Failed to show notification:", error);
    }
  }

  /**
   * Convert VAPID key to Uint8Array
   */
  urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, "+")
      .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  /**
   * Get permission status
   */
  getPermissionStatus() {
    return {
      supported: this.isSupported,
      permission: this.permissionStatus,
      subscribed: this.pushSubscription !== null,
    };
  }
}

// Initialize push notification manager when DOM is loaded
let pushManager = null;

document.addEventListener("DOMContentLoaded", () => {
  console.log("[Push] Initializing Push Notification Manager...");
  pushManager = new PushNotificationManager();

  // Expose to window for debugging
  window.pushManager = pushManager;

  // Auto-request permission after 3 seconds if not already granted or denied
  setTimeout(() => {
    if (pushManager.permissionStatus === "default") {
      console.log("[Push] Auto-requesting permission...");
      pushManager.requestPermission();
    }
  }, 3000);
});

// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = PushNotificationManager;
}
