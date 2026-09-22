document.addEventListener('DOMContentLoaded', () => {
    gsap.registerPlugin(ScrollTrigger);

    ScrollTrigger.matchMedia({
        "(min-width: 1025px)": function() {
            gsap.from(".stat-card, .chart-container, .action-card, .quick-action-card, .management-section", {
                y: 50,
                opacity: 0,
                duration: 0.8,
                stagger: 0.15,
                ease: "power4.out",
                scrollTrigger: {
                    trigger: ".main-content",
                    start: "top 80%"
                }
            });

            const pageTitle = document.querySelector('.page-title h1');
            const sectionHeaders = document.querySelectorAll('.section-header h2');
            
            if (pageTitle) {
                gsap.from(pageTitle, {
                    y: 40,
                    opacity: 0,
                    duration: 1,
                    ease: "power4.out"
                });
            }

            sectionHeaders.forEach((header, index) => {
                gsap.from(header, {
                    y: 30,
                    opacity: 0,
                    duration: 0.8,
                    delay: 0.2 + (index * 0.1),
                    ease: "power4.out",
                    scrollTrigger: {
                        trigger: header,
                        start: "top 90%"
                    }
                });
            });

            const cards = document.querySelectorAll(".stat-card, .action-card, .quick-action-card");
            cards.forEach((card) => {
                card.addEventListener("mouseenter", () => {
                    gsap.to(card, {
                        scale: 1.03,
                        boxShadow: "0 15px 35px rgba(0, 0, 0, 0.15)",
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
                card.addEventListener("mouseleave", () => {
                    gsap.to(card, {
                        scale: 1,
                        boxShadow: "var(--shadow)",
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
            });
        },
        "(max-width: 767px)": function() {
            gsap.from(".stat-card, .chart-container, .action-card, .quick-action-card, .management-section", {
                y: 20,
                opacity: 0,
                duration: 0.3,
                stagger: 0.08,
                ease: "power2.out",
                scrollTrigger: {
                    trigger: ".main-content",
                    start: "top 85%"
                }
            });
        }
    });
});
