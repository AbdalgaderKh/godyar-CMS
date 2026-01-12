document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("themeToggle");
    const backToTop = document.getElementById("backToTop");
    const root = document.documentElement;

    // Theme toggle (light/dark)
    let dark = false;
    toggle.addEventListener("click", () => {
        dark = !dark;
        if (dark) {
            root.style.setProperty("--bg", "#111827");
            root.style.setProperty("--bg-alt", "#020617");
            root.style.setProperty("--text", "#f9fafb");
            root.style.setProperty("--muted", "#9ca3af");
            root.style.setProperty("--border", "#1f2937");
            toggle.textContent = "وضع نهاري";
        } else {
            root.style.setProperty("--bg", "#f5f5f5");
            root.style.setProperty("--bg-alt", "#ffffff");
            root.style.setProperty("--text", "#222");
            root.style.setProperty("--muted", "#777");
            root.style.setProperty("--border", "#e1e1e1");
            toggle.textContent = "وضع ليلي";
        }
    });

    // Back to top
    window.addEventListener("scroll", () => {
        if (window.scrollY > 200) {
            backToTop.style.display = "block";
        } else {
            backToTop.style.display = "none";
        }
    });

    backToTop.addEventListener("click", () => {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
});
