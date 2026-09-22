// scripts/report-generator.js
// NutriDeq Enterprise Clinical Report Generator
(function () {
    'use strict';

    window.generateClinicalReport = function (targetSelector = '.main-content', filename = 'NutriDeq-Clinical-Report.pdf') {
        const { jsPDF } = window.jspdf;
        const element = document.querySelector(targetSelector);
        
        if (!element) {
            console.error('Report target not found:', targetSelector);
            return;
        }

        // Visual feedback
        const btn = event.currentTarget;
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;

        console.log('Capturing clinical data...');

        html2canvas(element, {
            scale: 2, // Higher quality
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            
            // Add Branded header to PDF if it's multiple pages
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            
            // Output the PDF
            pdf.save(filename);
            
            // Reset button
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }).catch(err => {
            console.error('PDF Generation Error:', err);
            btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }, 2000);
        });
    };
})();
