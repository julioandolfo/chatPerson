// Navigation
const navbar = document.getElementById('navbar');
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

// Scroll effect on navbar
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Mobile menu toggle
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        navMenu.classList.toggle('active');
    });
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            navMenu.classList.remove('active');
        }
    });
});

// Animated counters
function animateCounter(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target.toFixed(1);
            clearInterval(timer);
        } else {
            element.textContent = current.toFixed(1);
        }
    }, 16);
}

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const element = entry.target;
            
            // Animate counters
            if (element.classList.contains('stat-number')) {
                const target = parseFloat(element.getAttribute('data-target'));
                animateCounter(element, target);
                observer.unobserve(element);
            }
            
            // Animate metric values
            if (element.classList.contains('metric-value')) {
                const target = parseFloat(element.getAttribute('data-target'));
                animateCounter(element, target);
                observer.unobserve(element);
            }
            
            // Fade in animation
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe all animated elements
document.querySelectorAll('.stat-number, .metric-value, .feature-card, .showcase-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
    observer.observe(el);
});

// Chat Messages Animation
const chatMessages = document.getElementById('chatMessages');
const demoMessages = [
    {
        text: 'Olá! Gostaria de saber sobre seus produtos.',
        type: 'received',
        time: '14:23'
    },
    {
        text: 'Olá João! Fico feliz em ajudar. Qual produto te interessa?',
        type: 'sent',
        time: '14:24'
    },
    {
        text: 'Estou procurando uma solução para minha empresa.',
        type: 'received',
        time: '14:25'
    },
    {
        text: 'Perfeito! Temos várias opções. Posso enviar um catálogo completo?',
        type: 'sent',
        time: '14:26'
    }
];

let currentMessageIndex = 0;

function addChatMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${message.type}`;
    
    if (message.type === 'typing') {
        messageDiv.innerHTML = `
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="message-content">${message.text}</div>
            <div class="message-time">${message.time}</div>
        `;
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function simulateChat() {
    if (currentMessageIndex < demoMessages.length) {
        const message = demoMessages[currentMessageIndex];
        
        // Show typing indicator
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message received typing';
        typingDiv.innerHTML = `
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Remove typing and add message after delay
        setTimeout(() => {
            typingDiv.remove();
            addChatMessage(message);
            currentMessageIndex++;
            
            if (currentMessageIndex < demoMessages.length) {
                setTimeout(simulateChat, 2000);
            } else {
                // Reset after showing all messages
                setTimeout(() => {
                    chatMessages.innerHTML = '';
                    currentMessageIndex = 0;
                    setTimeout(simulateChat, 1000);
                }, 5000);
            }
        }, 1500);
    }
}

// Start chat simulation when hero section is visible
const heroObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && chatMessages) {
            setTimeout(simulateChat, 1000);
            heroObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

const heroSection = document.querySelector('.hero');
if (heroSection) {
    heroObserver.observe(heroSection);
}

// Demo Tabs
const demoTabs = document.querySelectorAll('.demo-tab');
const demoPanels = document.querySelectorAll('.demo-panel');

demoTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const targetTab = tab.getAttribute('data-tab');
        
        // Remove active class from all tabs and panels
        demoTabs.forEach(t => t.classList.remove('active'));
        demoPanels.forEach(p => p.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding panel
        tab.classList.add('active');
        const targetPanel = document.getElementById(`demo-${targetTab}`);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }
    });
});

// SLA Chart (using Canvas)
const slaChart = document.getElementById('slaChart');
if (slaChart) {
    const ctx = slaChart.getContext('2d');
    const chartContainer = slaChart.parentElement;
    
    // Set canvas size
    slaChart.width = chartContainer.clientWidth;
    slaChart.height = 300;
    
    // Sample data
    const data = {
        labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
        compliance: [95, 97, 98.5, 98.5],
        response: [3.2, 2.8, 2.5, 2.5]
    };
    
    function drawChart() {
        const width = slaChart.width;
        const height = slaChart.height;
        const padding = 40;
        const chartWidth = width - padding * 2;
        const chartHeight = height - padding * 2;
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        // Draw grid
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.lineWidth = 1;
        
        for (let i = 0; i <= 5; i++) {
            const y = padding + (chartHeight / 5) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }
        
        // Draw compliance line
        ctx.strokeStyle = '#6366f1';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        data.compliance.forEach((value, index) => {
            const x = padding + (chartWidth / (data.labels.length - 1)) * index;
            const y = padding + chartHeight - ((value - 90) / 10) * chartHeight;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();
        
        // Draw response line
        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        data.response.forEach((value, index) => {
            const x = padding + (chartWidth / (data.labels.length - 1)) * index;
            const y = padding + chartHeight - ((value - 1) / 3) * chartHeight;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        ctx.stroke();
        
        // Draw points
        data.compliance.forEach((value, index) => {
            const x = padding + (chartWidth / (data.labels.length - 1)) * index;
            const y = padding + chartHeight - ((value - 90) / 10) * chartHeight;
            
            ctx.fillStyle = '#6366f1';
            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fill();
        });
        
        data.response.forEach((value, index) => {
            const x = padding + (chartWidth / (data.labels.length - 1)) * index;
            const y = padding + chartHeight - ((value - 1) / 3) * chartHeight;
            
            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fill();
        });
        
        // Draw labels
        ctx.fillStyle = '#94a3b8';
        ctx.font = '12px Inter';
        ctx.textAlign = 'center';
        
        data.labels.forEach((label, index) => {
            const x = padding + (chartWidth / (data.labels.length - 1)) * index;
            ctx.fillText(label, x, height - 10);
        });
        
        // Draw Y-axis labels
        ctx.textAlign = 'right';
        for (let i = 0; i <= 5; i++) {
            const y = padding + (chartHeight / 5) * i;
            const value = 100 - (i * 2);
            ctx.fillText(value + '%', padding - 10, y + 4);
        }
    }
    
    // Draw chart when visible
    const chartObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                drawChart();
                chartObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    chartObserver.observe(slaChart);
    
    // Redraw on resize
    window.addEventListener('resize', () => {
        slaChart.width = chartContainer.clientWidth;
        drawChart();
    });
}

// Form submission
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(contactForm);
        const data = Object.fromEntries(formData);
        
        // Simulate form submission
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        submitBtn.textContent = 'Enviando...';
        submitBtn.disabled = true;
        
        setTimeout(() => {
            submitBtn.textContent = 'Enviado com Sucesso! ✓';
            submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            
            setTimeout(() => {
                contactForm.reset();
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                submitBtn.style.background = '';
            }, 3000);
        }, 1500);
    });
}

// Parallax effect for hero orbs
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const orbs = document.querySelectorAll('.gradient-orb');
    
    orbs.forEach((orb, index) => {
        const speed = 0.5 + (index * 0.2);
        const yPos = -(scrolled * speed);
        orb.style.transform = `translateY(${yPos}px)`;
    });
});

// Kanban drag and drop simulation
const kanbanCards = document.querySelectorAll('.kanban-card');
kanbanCards.forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.cursor = 'grab';
    });
    
    card.addEventListener('mousedown', () => {
        card.style.cursor = 'grabbing';
        card.style.opacity = '0.8';
    });
    
    card.addEventListener('mouseup', () => {
        card.style.cursor = 'grab';
        card.style.opacity = '1';
    });
});

// Animate feature cards on hover
const featureCards = document.querySelectorAll('.feature-card');
featureCards.forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-8px) scale(1.02)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0) scale(1)';
    });
});

// Animate AI conversation
const aiMessages = document.querySelectorAll('.ai-message');
aiMessages.forEach((message, index) => {
    message.style.opacity = '0';
    message.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        message.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        message.style.opacity = '1';
        message.style.transform = 'translateY(0)';
    }, index * 500);
});

// Real-time metric updates simulation
function updateMetrics() {
    const metrics = document.querySelectorAll('.metric-value');
    metrics.forEach(metric => {
        const currentValue = parseFloat(metric.textContent);
        const variation = (Math.random() - 0.5) * 0.1;
        const newValue = Math.max(0, currentValue + variation);
        
        if (metric.getAttribute('data-target')) {
            const target = parseFloat(metric.getAttribute('data-target'));
            if (Math.abs(newValue - target) < 0.1) {
                return; // Don't update if close to target
            }
        }
        
        // Only update if not being animated
        if (!metric.classList.contains('animating')) {
            metric.textContent = newValue.toFixed(1);
        }
    });
}

// Update metrics every 5 seconds (simulation)
// setInterval(updateMetrics, 5000);

// Initialize animations on load
window.addEventListener('load', () => {
    // Animate hero stats
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const target = parseFloat(stat.getAttribute('data-target'));
        if (target) {
            animateCounter(stat, target);
        }
    });
    
    // Add loading animation
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease-in';
        document.body.style.opacity = '1';
    }, 100);
});

// Smooth reveal animations
const revealElements = document.querySelectorAll('.showcase-item, .feature-card, .ai-card');
revealElements.forEach((element, index) => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(30px)';
    
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    revealObserver.observe(element);
});

// Console welcome message
console.log('%c🚀 Chat System - Landing Page', 'font-size: 20px; font-weight: bold; color: #6366f1;');
console.log('%cSistema completo de atendimento multicanal com IA', 'font-size: 14px; color: #94a3b8;');
