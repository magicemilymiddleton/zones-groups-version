// Enhanced Reports JavaScript
jQuery(document).ready(function($) {
    
    // Course card click handler
    $('.llmsgaa-course-report-card').on('click', function() {
        // Don't proceed if this card has no enrollments
        if ($(this).hasClass('no-enrollments')) {
            return;
        }
        
        const courseId = $(this).data('course-id');
        if (courseId) {
            loadCourseReport(courseId);
        }
    });
    
    // View report button click (alternative to card click)
    $(document).on('click', '.llmsgaa-view-report-btn', function(e) {
        e.stopPropagation();
        const courseId = $(this).data('course-id');
        if (courseId) {
            loadCourseReport(courseId);
        }
    });
    
    // Back to courses button
    $(document).on('click', '#llmsgaa-back-to-courses', function() {
        showCourseSelection();
    });
    
    // View student details button
    $(document).on('click', '.llmsgaa-view-details-btn', function() {
        const studentId = $(this).data('student-id');
        const courseId = $(this).data('course-id');
        showStudentDetails(studentId, courseId);
    });
    
    // Close modal handlers
    $(document).on('click', '.llmsgaa-close-modal, .llmsgaa-modal-overlay', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Escape key to close modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    /**
     * Load and display course report
     */
    function loadCourseReport(courseId) {
        // Show loading state
        const $courseSelection = $('.llmsgaa-course-selection');
        const $reportResults = $('#llmsgaa-report-results');
        const $reportContent = $('#llmsgaa-report-content');
        
        // Add loading indicator
        $reportContent.html(`
            <div class="llmsgaa-loading-container" style="text-align: center; padding: 60px 20px;">
                <div class="llmsgaa-loading-spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top: 20px; color: #666; font-size: 16px;">Loading course report...</p>
            </div>
        `);
        
        // Show results section
        $courseSelection.hide();
        $reportResults.show();
        
        // Scroll to top of results
        $reportResults[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Make AJAX request
        $.ajax({
            url: LLMSGAA_Reports.ajax_url,
            type: 'POST',
            data: {
                action: 'llmsgaa_get_course_report',
                course_id: courseId,
                group_id: LLMSGAA_Reports.group_id,
                nonce: LLMSGAA_Reports.nonce
            },
            success: function(response) {
                $reportContent.html(response);
                
                // Add entrance animation
                $reportContent.css('opacity', '0').animate({ opacity: 1 }, 300);
            },
            error: function(xhr, status, error) {
                console.error('Report loading error:', error);
                $reportContent.html(`
                    <div class="llmsgaa-notice llmsgaa-notice-error" style="background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">
                        <h3>Error Loading Report</h3>
                        <p>Unable to load the course report. Please try again.</p>
                        <button class="llmsgaa-back-btn" onclick="showCourseSelection()">
                            <span class="llmsgaa-back-icon">←</span> Back to Course Selection
                        </button>
                    </div>
                `);
            }
        });
    }
    
    /**
     * Show course selection screen
     */
    function showCourseSelection() {
        $('.llmsgaa-course-selection').show();
        $('#llmsgaa-report-results').hide();
        
        // Scroll back to top
        $('.llmsgaa-course-selection')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    /**
     * Show student details modal
     */
    function showStudentDetails(studentId, courseId) {
        // Create modal overlay
        if (!$('.llmsgaa-modal-overlay').length) {
            $('body').append('<div class="llmsgaa-modal-overlay"></div>');
        }
        
        // Show loading in modal
        const loadingModal = `
            <div class="llmsgaa-student-detail-modal">
                <div class="llmsgaa-modal-header">
                    <h4>Loading Student Details...</h4>
                    <button class="llmsgaa-close-modal">&times;</button>
                </div>
                <div class="llmsgaa-modal-content">
                    <div style="text-align: center; padding: 40px;">
                        <div style="display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #0073aa; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                        <p style="margin-top: 15px; color: #666;">Loading detailed progress...</p>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(loadingModal);
        
        // Add entrance animation
        $('.llmsgaa-student-detail-modal').css({
            opacity: 0,
            transform: 'translate(-50%, -50%) scale(0.9)'
        }).animate({
            opacity: 1
        }, 200, function() {
            $(this).css('transform', 'translate(-50%, -50%) scale(1)');
        });
        
        // Load student details
        $.ajax({
            url: LLMSGAA_Reports.ajax_url,
            type: 'POST',
            data: {
                action: 'llmsgaa_get_student_detail',
                student_id: studentId,
                course_id: courseId,
                nonce: LLMSGAA_Reports.nonce
            },
            success: function(response) {
                // Replace loading modal with actual content
                $('.llmsgaa-student-detail-modal').remove();
                $('body').append(response);
                
                // Add entrance animation for new modal
                $('.llmsgaa-student-detail-modal').css({
                    opacity: 0,
                    transform: 'translate(-50%, -50%) scale(0.9)'
                }).animate({
                    opacity: 1
                }, 200, function() {
                    $(this).css('transform', 'translate(-50%, -50%) scale(1)');
                });
            },
            error: function(xhr, status, error) {
                console.error('Student details error:', error);
                $('.llmsgaa-student-detail-modal').remove();
                
                // Show error modal
                const errorModal = `
                    <div class="llmsgaa-student-detail-modal">
                        <div class="llmsgaa-modal-header">
                            <h4>Error Loading Details</h4>
                            <button class="llmsgaa-close-modal">&times;</button>
                        </div>
                        <div class="llmsgaa-modal-content">
                            <div class="llmsgaa-notice llmsgaa-notice-error" style="background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; margin: 0;">
                                <p>Unable to load student details. Please try again.</p>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(errorModal);
            }
        });
    }
    
    /**
     * Close modal
     */
    function closeModal() {
        $('.llmsgaa-student-detail-modal').animate({
            opacity: 0
        }, 200, function() {
            $(this).remove();
        });
        
        $('.llmsgaa-modal-overlay').fadeOut(200, function() {
            $(this).remove();
        });
    }
    
    /**
     * Add hover effects to course cards
     */
    $('.llmsgaa-course-report-card:not(.no-enrollments)').hover(
        function() {
            $(this).css('border-left-color', '#005a87');
        },
        function() {
            $(this).css('border-left-color', '#0073aa');
        }
    );
    
    /**
     * Add click animation to buttons
     */
    $(document).on('mousedown', '.llmsgaa-view-report-btn, .llmsgaa-view-details-btn, .llmsgaa-back-btn', function() {
        $(this).css('transform', 'translateY(0)');
    });
    
    $(document).on('mouseup mouseleave', '.llmsgaa-view-report-btn, .llmsgaa-view-details-btn, .llmsgaa-back-btn', function() {
        $(this).css('transform', '');
    });
    
    // Make global functions available
    window.showCourseSelection = showCourseSelection;
    window.loadCourseReport = loadCourseReport;
    window.showStudentDetails = showStudentDetails;
    window.closeModal = closeModal;
});

// Add loading spinner animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);