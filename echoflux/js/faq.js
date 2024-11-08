    document.addEventListener('DOMContentLoaded', () => {
        const faqQuestions = document.querySelectorAll('.faq-question');

        faqQuestions.forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                
                if (answer.style.display === "block") {
                    answer.style.display = "none";
                    question.classList.remove('open');
                } else {
                    answer.style.display = "block";
                    question.classList.add('open');
                }
            });
        });
    });
