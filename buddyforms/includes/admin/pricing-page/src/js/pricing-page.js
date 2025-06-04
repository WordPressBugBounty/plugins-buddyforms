// Vanilla JS (new)
class BuddyFormsPricingPage {
  constructor() {
      this.purchaseButtons = document.querySelectorAll("[data-purchase-licenses]");
      this.productId = "2046";
      this.planId = "4316";
      this.publicKey = "pk_ee958df753d34648b465568a836aa";

      this.purchaseHandler = null;

    this.init();
  }

  init() {
    this.purchaseHandler = new FS.Checkout({
      product_id: this.productId,
      plan_id: this.planId,
      public_key: this.publicKey,
    });

    this.purchaseButtons.forEach((button) => {
      button.addEventListener("click", (e) => {
        this.purchasePlanHandler(e);
      });
    });
  }

  purchasePlanHandler(e) {
    const licenses = e.target.dataset.purchaseLicenses;
    this.purchaseHandler.open({
      licenses: licenses,
    });
  }
}

document.addEventListener("DOMContentLoaded", function () {
  bfPricingPage = new BuddyFormsPricingPage();
});
