// Enhanced Bulk Assignment System with Multi-Pass Support for LLMS Groups Access Addon
// Version 2.0.0 - Fixed version with proper data handling

jQuery(document).ready(function($) {
    
    // Enhanced Bulk Assignment Modal with Multi-Pass Support
    window.showEnhancedBulkAssignModal = function(selectedEmails) {
        console.log('Loading enhanced bulk assign modal for:', selectedEmails);
        
        const group_id = window.llmsgaa_group_id || window.groupId || $('#group_id').val();
        const nonce = window.ajaxNonce || window.llmsgaa_bulk?.nonce || $('#ajax_nonce').val();
        
        // First, get available licenses
        $.ajax({
            url: window.ajaxurl || window.llmsgaa_bulk?.ajax_url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'llmsgaa_get_available_licenses_detailed',
                group_id: group_id,
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    createEnhancedModalWithMultiPass(selectedEmails, response.data);
                } else {
                    alert('No available licenses found for this group.');
                }
            },
            error: function(xhr) {
                console.error('Error loading licenses:', xhr.responseText);
                alert('Error loading available licenses. Please check console for details.');
            }
        });
    };
    
    function createEnhancedModalWithMultiPass(selectedEmails, licensesData) {
        // Group licenses by course/pass type
        const courseGroups = {};
        const passInventory = {};
        
        licensesData.forEach(license => {
            const courseId = license.course_id;
            const courseTitle = license.course_title;
            const passKey = `${license.pass_id}_${license.course_id}`;
            
            if (!courseGroups[courseId]) {
                courseGroups[courseId] = {
                    title: courseTitle,
                    passes: [],
                    available: 0
                };
            }
            
            if (!passInventory[passKey]) {
                passInventory[passKey] = {
                    pass_id: license.pass_id,
                    course_id: courseId,
                    course_title: courseTitle,
                    pass_title: license.pass_title,
                    start_date: license.start_date,
                    licenses: [],
                    available: 0
                };
            }
            
            courseGroups[courseId].passes.push(license);
            courseGroups[courseId].available++;
            passInventory[passKey].licenses.push(license);
            passInventory[passKey].available++;
        });
        
        // Initialize member assignments with multi-pass support
        const memberAssignments = {};
        selectedEmails.forEach(email => {
            memberAssignments[email] = {
                courses: {}, // courseId -> passKey mapping
                passes: []   // Array of assigned pass keys
            };
        });
        
        // Build modal HTML with multi-pass support
        let modalHTML = `
        <div id="enhanced-bulk-modal" class="llmsgaa-modal-overlay" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <div class="llmsgaa-modal-content" style="
                background: white;
                width: 95%;
                max-width: 1200px;
                height: 90vh;
                overflow: hidden;
                border-radius: 8px;
                display: flex;
                flex-direction: column;
            ">
                <!-- Header -->
                <div style="
                    padding: 20px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                ">
                    <h2 style="margin: 0; font-size: 22px; color: white;">
                        🎯 Advanced Multi-Pass Assignment System
                    </h2>
                    <button onclick="closeEnhancedModal()" style="
                        background: rgba(255,255,255,0.2);
                        border: none;
                        font-size: 24px;
                        cursor: pointer;
                        color: white;
                        padding: 0 8px;
                        border-radius: 4px;
                    ">×</button>
                </div>
                
                <!-- Quick Assignment Tools -->
                <div style="
                    padding: 15px 20px;
                    background: #f0f4f8;
                    border-bottom: 1px solid #e5e7eb;
                ">
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 250px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px; font-size: 13px;">
                                Quick Assignment Patterns:
                            </label>
                            <select id="assignment-pattern" style="
                                width: 100%;
                                padding: 8px 12px;
                                border: 1px solid #cbd5e0;
                                border-radius: 6px;
                                font-size: 14px;
                                background: white;
                            ">
                                <option value="">-- Choose Pattern --</option>
                                <option value="all-same">All members get same courses</option>
                                <option value="distribute-single">Each member gets one course (distributed)</option>
                                <option value="distribute-all">Each member gets all available courses</option>
                                <option value="custom">Custom assignment per member</option>
                            </select>
                        </div>
                        
                        <div id="pattern-options" style="flex: 1; min-width: 250px; display: none;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px; font-size: 13px;">
                                Select Courses for Pattern:
                            </label>
                            <div style="
                                background: white;
                                padding: 8px;
                                border: 1px solid #cbd5e0;
                                border-radius: 6px;
                                max-height: 60px;
                                overflow-y: auto;
                            ">
                                ${Object.entries(courseGroups).map(([courseId, course]) => `
                                    <label style="display: block; margin: 4px 0;">
                                        <input type="checkbox" class="pattern-course" value="${courseId}">
                                        ${course.title} (${course.available} available)
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                        
                        <button id="apply-pattern-btn" onclick="applyAssignmentPattern()" style="
                            padding: 8px 20px;
                            background: #4299e1;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            font-size: 14px;
                            display: none;
                        ">
                            Apply Pattern
                        </button>
                    </div>
                </div>
                
                <!-- Available Inventory Summary -->
                <div style="
                    padding: 12px 20px;
                    background: #e6fffa;
                    border-bottom: 1px solid #e5e7eb;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #234e52;">Available Pass Inventory:</strong>
                        </div>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            ${Object.entries(courseGroups).map(([courseId, course]) => `
                                <div class="inventory-badge" data-course="${courseId}" style="
                                    background: white;
                                    padding: 4px 10px;
                                    border-radius: 20px;
                                    border: 2px solid #48bb78;
                                    font-size: 13px;
                                ">
                                    <strong>${course.title}:</strong>
                                    <span class="inventory-count" data-course="${courseId}">${course.available}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- Member Assignment Grid -->
                <div style="
                    flex: 1;
                    overflow-y: auto;
                    padding: 20px;
                    background: #fafbfc;
                ">
                    <table id="multi-pass-assignment-table" style="
                        width: 100%;
                        border-collapse: separate;
                        border-spacing: 0 8px;
                    ">
                        <thead>
                            <tr style="background: #2d3748; color: white;">
                                <th style="padding: 12px; text-align: left; border-radius: 6px 0 0 6px;">
                                    Member Email
                                </th>
                                <th style="padding: 12px; text-align: left;">
                                    Assigned Courses
                                </th>
                                <th style="padding: 12px; text-align: center; border-radius: 0 6px 6px 0;">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            ${selectedEmails.map((email, index) => `
                                <tr class="member-assignment-row" data-email="${email}" style="
                                    background: white;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                ">
                                    <td style="
                                        padding: 15px;
                                        border-left: 4px solid #667eea;
                                        font-weight: 500;
                                    ">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="member-number" style="
                                                background: #667eea;
                                                color: white;
                                                width: 24px;
                                                height: 24px;
                                                border-radius: 50%;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                font-size: 12px;
                                                font-weight: bold;
                                            ">${index + 1}</span>
                                            ${email}
                                        </div>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div class="assigned-courses-container" data-email="${email}" style="
                                            display: flex;
                                            flex-wrap: wrap;
                                            gap: 8px;
                                            min-height: 32px;
                                            align-items: center;
                                        ">
                                            <span class="no-courses-text" style="
                                                color: #a0aec0;
                                                font-style: italic;
                                            ">No courses assigned</span>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <button onclick="openCourseSelector('${email}')" style="
                                            padding: 6px 12px;
                                            background: #48bb78;
                                            color: white;
                                            border: none;
                                            border-radius: 4px;
                                            cursor: pointer;
                                            font-size: 13px;
                                            font-weight: 500;
                                        ">
                                            + Add Courses
                                        </button>
                                        <button onclick="clearMemberAssignments('${email}')" style="
                                            padding: 6px 12px;
                                            background: #fc8181;
                                            color: white;
                                            border: none;
                                            border-radius: 4px;
                                            cursor: pointer;
                                            font-size: 13px;
                                            font-weight: 500;
                                            margin-left: 5px;
                                        ">
                                            Clear
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Footer -->
                <div style="
                    padding: 20px;
                    border-top: 2px solid #e5e7eb;
                    background: white;
                ">
                    <div id="assignment-summary" style="
                        margin-bottom: 15px;
                        padding: 12px;
                        background: #f7fafc;
                        border-left: 4px solid #4299e1;
                        border-radius: 4px;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Assignment Summary:</strong>
                                <span id="summary-details" style="margin-left: 10px;">
                                    0 passes assigned to 0 members
                                </span>
                            </div>
                            <div id="summary-badges" style="display: flex; gap: 8px;">
                                <!-- Course assignment counts will go here -->
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; gap: 10px;">
                            <button onclick="validateAssignments()" style="
                                padding: 10px 20px;
                                background: #805ad5;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 600;
                            ">
                                🔍 Validate
                            </button>
                            <button onclick="previewAssignments()" style="
                                padding: 10px 20px;
                                background: #3182ce;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 600;
                            ">
                                👁 Preview
                            </button>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button onclick="closeEnhancedModal()" style="
                                padding: 10px 20px;
                                background: #e2e8f0;
                                color: #2d3748;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 600;
                            ">
                                Cancel
                            </button>
                            <button id="confirm-multi-assignments-btn" onclick="confirmMultiAssignments()" style="
                                padding: 10px 20px;
                                background: #48bb78;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                                font-weight: 600;
                                position: relative;
                            " disabled>
                                ✅ Confirm Assignments
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        
        // Add modal to body
        $('body').append(modalHTML);
        
        // Store data globally
        window.bulkAssignData = {
            selectedEmails,
            courseGroups,
            passInventory,
            memberAssignments
        };
        
        // Initialize handlers
        initializeMultiPassHandlers();
    }
    
    // Course Selector Modal
    window.openCourseSelector = function(email) {
        const { courseGroups, memberAssignments } = window.bulkAssignData;
        
        let selectorHTML = `
        <div id="course-selector-modal" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999999;
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <div style="
                background: white;
                width: 500px;
                max-height: 600px;
                border-radius: 8px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            ">
                <div style="
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                ">
                    <h3 style="margin: 0; color: white;">Select Courses for ${email}</h3>
                </div>
                <div style="
                    flex: 1;
                    overflow-y: auto;
                    padding: 20px;
                ">
                    <div style="margin-bottom: 15px;">
                        <strong>Available Courses:</strong>
                    </div>
                    ${Object.entries(courseGroups).map(([courseId, course]) => {
                        const isAssigned = memberAssignments[email].courses[courseId];
                        const availableCount = getAvailableCountForCourse(courseId);
                        const canAssign = availableCount > 0 && !isAssigned;
                        
                        return `
                        <div style="
                            padding: 12px;
                            margin-bottom: 10px;
                            border: 2px solid ${isAssigned ? '#48bb78' : '#e2e8f0'};
                            border-radius: 6px;
                            background: ${isAssigned ? '#f0fdf4' : 'white'};
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" 
                                               class="course-selector-checkbox" 
                                               value="${courseId}"
                                               data-email="${email}"
                                               ${isAssigned ? 'checked' : ''}
                                               ${!canAssign && !isAssigned ? 'disabled' : ''}>
                                        <span style="${!canAssign && !isAssigned ? 'opacity: 0.5' : ''}">
                                            ${course.title}
                                        </span>
                                    </label>
                                </div>
                                <div style="
                                    padding: 4px 8px;
                                    background: ${availableCount > 0 ? '#d1fae5' : '#fee2e2'};
                                    color: ${availableCount > 0 ? '#065f46' : '#991b1b'};
                                    border-radius: 4px;
                                    font-size: 12px;
                                    font-weight: 600;
                                ">
                                    ${availableCount} available
                                </div>
                            </div>
                            ${isAssigned ? '<div style="color: #059669; font-size: 12px; margin-top: 5px;">✓ Already assigned</div>' : ''}
                        </div>
                        `;
                    }).join('')}
                </div>
                <div style="
                    padding: 20px;
                    border-top: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                ">
                    <button onclick="$('#course-selector-modal').remove()" style="
                        padding: 8px 16px;
                        background: #e2e8f0;
                        color: #2d3748;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                    ">
                        Cancel
                    </button>
                    <button onclick="applyCourseSelection('${email}')" style="
                        padding: 8px 16px;
                        background: #667eea;
                        color: white;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                    ">
                        Apply Selection
                    </button>
                </div>
            </div>
        </div>`;
        
        $('body').append(selectorHTML);
    };
    
    // Apply course selection
    window.applyCourseSelection = function(email) {
        const { memberAssignments, passInventory } = window.bulkAssignData;
        
        // Get selected courses
        const selectedCourses = [];
        $('.course-selector-checkbox:checked').each(function() {
            selectedCourses.push($(this).val());
        });
        
        // Clear current assignments for this member
        memberAssignments[email] = {
            courses: {},
            passes: []
        };
        
        // Assign selected courses
        selectedCourses.forEach(courseId => {
            // Find available pass for this course
            const passKey = findAvailablePassForCourse(courseId);
            if (passKey) {
                memberAssignments[email].courses[courseId] = passKey;
                memberAssignments[email].passes.push(passKey);
            }
        });
        
        // Update display
        updateMemberAssignmentDisplay(email);
        updateInventoryCounts();
        updateSummary();
        
        // Close selector
        $('#course-selector-modal').remove();
    };
    
    // Helper functions
    function findAvailablePassForCourse(courseId) {
        const { passInventory, memberAssignments } = window.bulkAssignData;
        
        // Find all passes for this course
        for (const [passKey, passInfo] of Object.entries(passInventory)) {
            if (passInfo.course_id == courseId) {
                // Count how many times this pass is already assigned
                let assignedCount = 0;
                Object.values(memberAssignments).forEach(assignment => {
                    if (assignment.passes.includes(passKey)) {
                        assignedCount++;
                    }
                });
                
                if (assignedCount < passInfo.available) {
                    return passKey;
                }
            }
        }
        return null;
    }
    
    function getAvailableCountForCourse(courseId) {
        const { passInventory, memberAssignments } = window.bulkAssignData;
        let totalAvailable = 0;
        
        for (const [passKey, passInfo] of Object.entries(passInventory)) {
            if (passInfo.course_id == courseId) {
                let assignedCount = 0;
                Object.values(memberAssignments).forEach(assignment => {
                    if (assignment.passes.includes(passKey)) {
                        assignedCount++;
                    }
                });
                totalAvailable += Math.max(0, passInfo.available - assignedCount);
            }
        }
        
        return totalAvailable;
    }
    
    function updateMemberAssignmentDisplay(email) {
        const { memberAssignments, courseGroups } = window.bulkAssignData;
        const assignment = memberAssignments[email];
        const container = $(`.assigned-courses-container[data-email="${email}"]`);
        
        container.empty();
        
        if (Object.keys(assignment.courses).length === 0) {
            container.html('<span class="no-courses-text" style="color: #a0aec0; font-style: italic;">No courses assigned</span>');
        } else {
            Object.keys(assignment.courses).forEach(courseId => {
                const course = courseGroups[courseId];
                container.append(`
                    <div class="course-badge" style="
                        background: linear-gradient(135deg, #667eea, #764ba2);
                        color: white;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 13px;
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                    ">
                        ${course.title}
                        <button onclick="removeCourseFromMember('${email}', '${courseId}')" style="
                            background: rgba(255,255,255,0.3);
                            border: none;
                            color: white;
                            cursor: pointer;
                            padding: 0 4px;
                            border-radius: 50%;
                            font-size: 16px;
                            line-height: 1;
                        ">×</button>
                    </div>
                `);
            });
        }
    }
    
    window.removeCourseFromMember = function(email, courseId) {
        const { memberAssignments } = window.bulkAssignData;
        const passKey = memberAssignments[email].courses[courseId];
        
        delete memberAssignments[email].courses[courseId];
        memberAssignments[email].passes = memberAssignments[email].passes.filter(p => p !== passKey);
        
        updateMemberAssignmentDisplay(email);
        updateInventoryCounts();
        updateSummary();
    };
    
    window.clearMemberAssignments = function(email) {
        const { memberAssignments } = window.bulkAssignData;
        memberAssignments[email] = {
            courses: {},
            passes: []
        };
        
        updateMemberAssignmentDisplay(email);
        updateInventoryCounts();
        updateSummary();
    };
    
    function updateInventoryCounts() {
        const { courseGroups } = window.bulkAssignData;
        
        Object.keys(courseGroups).forEach(courseId => {
            const available = getAvailableCountForCourse(courseId);
            $(`.inventory-count[data-course="${courseId}"]`).text(available);
            
            const badge = $(`.inventory-badge[data-course="${courseId}"]`);
            if (available === 0) {
                badge.css('border-color', '#fc8181').css('background', '#fee2e2');
            } else if (available < 3) {
                badge.css('border-color', '#f6ad55').css('background', '#fef5e7');
            } else {
                badge.css('border-color', '#48bb78').css('background', 'white');
            }
        });
    }
    
    function updateSummary() {
        const { memberAssignments, selectedEmails, courseGroups } = window.bulkAssignData;
        let totalPasses = 0;
        let membersWithAssignments = 0;
        const courseCounts = {};
        
        selectedEmails.forEach(email => {
            const assignment = memberAssignments[email];
            if (assignment.passes.length > 0) {
                membersWithAssignments++;
                totalPasses += assignment.passes.length;
                
                Object.keys(assignment.courses).forEach(courseId => {
                    courseCounts[courseId] = (courseCounts[courseId] || 0) + 1;
                });
            }
        });
        
        $('#summary-details').text(
            `${totalPasses} pass${totalPasses !== 1 ? 'es' : ''} assigned to ${membersWithAssignments} member${membersWithAssignments !== 1 ? 's' : ''}`
        );
        
        // Update course badges
        const badgesHTML = Object.entries(courseCounts).map(([courseId, count]) => {
            const course = courseGroups[courseId];
            return `
                <span style="
                    background: #edf2f7;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                ">
                    ${course.title}: ${count}
                </span>
            `;
        }).join('');
        
        $('#summary-badges').html(badgesHTML);
        
        // Enable/disable confirm button
        $('#confirm-multi-assignments-btn').prop('disabled', totalPasses === 0);
    }
    
    function initializeMultiPassHandlers() {
        // Pattern selector
        $('#assignment-pattern').on('change', function() {
            const pattern = $(this).val();
            if (pattern === 'all-same' || pattern === 'distribute-all') {
                $('#pattern-options').show();
                $('#apply-pattern-btn').show();
            } else if (pattern === 'distribute-single') {
                $('#pattern-options').hide();
                $('#apply-pattern-btn').show();
            } else {
                $('#pattern-options').hide();
                $('#apply-pattern-btn').hide();
            }
        });
    }
    
    // Apply assignment patterns
    window.applyAssignmentPattern = function() {
        const pattern = $('#assignment-pattern').val();
        const { selectedEmails, courseGroups, memberAssignments } = window.bulkAssignData;
        
        // Clear all assignments first
        selectedEmails.forEach(email => {
            memberAssignments[email] = { courses: {}, passes: [] };
        });
        
        if (pattern === 'all-same') {
            // All members get the selected courses
            const selectedCourses = [];
            $('.pattern-course:checked').each(function() {
                selectedCourses.push($(this).val());
            });
            
            selectedEmails.forEach(email => {
                selectedCourses.forEach(courseId => {
                    const passKey = findAvailablePassForCourse(courseId);
                    if (passKey) {
                        memberAssignments[email].courses[courseId] = passKey;
                        memberAssignments[email].passes.push(passKey);
                    }
                });
            });
            
        } else if (pattern === 'distribute-single') {
            // Distribute members across available courses
            const courseIds = Object.keys(courseGroups);
            selectedEmails.forEach((email, index) => {
                const courseId = courseIds[index % courseIds.length];
                const passKey = findAvailablePassForCourse(courseId);
                if (passKey) {
                    memberAssignments[email].courses[courseId] = passKey;
                    memberAssignments[email].passes.push(passKey);
                }
            });
            
        } else if (pattern === 'distribute-all') {
            // All members get all selected courses
            const selectedCourses = [];
            $('.pattern-course:checked').each(function() {
                selectedCourses.push($(this).val());
            });
            
            selectedEmails.forEach(email => {
                selectedCourses.forEach(courseId => {
                    const passKey = findAvailablePassForCourse(courseId);
                    if (passKey) {
                        memberAssignments[email].courses[courseId] = passKey;
                        memberAssignments[email].passes.push(passKey);
                    }
                });
            });
        }
        
        // Update all displays
        selectedEmails.forEach(email => {
            updateMemberAssignmentDisplay(email);
        });
        updateInventoryCounts();
        updateSummary();
    };
    
    // Validate assignments
    window.validateAssignments = function() {
        const { memberAssignments, selectedEmails } = window.bulkAssignData;
        let issues = [];
        
        selectedEmails.forEach(email => {
            if (memberAssignments[email].passes.length === 0) {
                issues.push(`${email} has no courses assigned`);
            }
        });
        
        // Check for over-allocation
        const courseIds = Object.keys(window.bulkAssignData.courseGroups);
        courseIds.forEach(courseId => {
            const available = getAvailableCountForCourse(courseId);
            if (available < 0) {
                issues.push(`Course "${window.bulkAssignData.courseGroups[courseId].title}" is over-allocated`);
            }
        });
        
        if (issues.length > 0) {
            alert('Validation Issues:\n\n' + issues.join('\n'));
        } else {
            alert('✅ All assignments are valid!');
        }
    };
    
    // Preview assignments
    window.previewAssignments = function() {
        const { memberAssignments, courseGroups } = window.bulkAssignData;
        
        let previewHTML = '<div style="max-height: 500px; overflow-y: auto;">';
        previewHTML += '<h3>Assignment Preview:</h3>';
        
        Object.entries(memberAssignments).forEach(([email, assignment]) => {
            if (assignment.passes.length > 0) {
                previewHTML += `<div style="margin-bottom: 10px; padding: 10px; background: #f7fafc; border-radius: 4px;">`;
                previewHTML += `<strong>${email}:</strong><br>`;
                Object.keys(assignment.courses).forEach(courseId => {
                    previewHTML += `&nbsp;&nbsp;• ${courseGroups[courseId].title}<br>`;
                });
                previewHTML += `</div>`;
            }
        });
        
        previewHTML += '</div>';
        
        // Create preview modal
        const previewModal = $(`
            <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; padding: 20px; border-radius: 8px; z-index: 99999999;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 600px; width: 90%;">
                ${previewHTML}
                <button onclick="$(this).parent().remove()" style="
                    margin-top: 15px; padding: 8px 16px; background: #667eea; color: white;
                    border: none; border-radius: 4px; cursor: pointer;">Close Preview</button>
            </div>
        `);
        
        $('body').append(previewModal);
    };
    
    // FIXED confirmMultiAssignments function
    window.confirmMultiAssignments = function() {
        console.log('=== Starting confirmMultiAssignments ===');
        
        // Get the stored data from the enhanced modal
        if (!window.bulkAssignData) {
            console.error('No bulk assign data found!');
            alert('Error: Assignment data not found. Please try again.');
            return;
        }
        
        const { memberAssignments, passInventory, selectedEmails } = window.bulkAssignData;
        const group_id = window.llmsgaa_group_id || window.groupId || $('#group_id').val();
        const nonce = window.ajaxNonce || window.llmsgaa_bulk?.nonce || $('#ajax_nonce').val();
        
        console.log('Debug - Bulk assign data:', {
            memberAssignments: memberAssignments,
            passInventory: passInventory,
            selectedEmails: selectedEmails,
            group_id: group_id,
            nonce: nonce
        });
        
        // Build assignment data array
        const assignmentData = [];
        
        // Process each member's assignments
        Object.entries(memberAssignments).forEach(([email, assignment]) => {
            console.log(`Processing assignments for ${email}:`, assignment);
            
            if (assignment.passes && assignment.passes.length > 0) {
                assignment.passes.forEach(passKey => {
                    const passInfo = passInventory[passKey];
                    if (passInfo) {
                        const assignmentEntry = {
                            email: email,
                            pass_id: passInfo.pass_id,
                            course_id: passInfo.course_id,
                            pass_key: passKey
                        };
                        assignmentData.push(assignmentEntry);
                        console.log('Added assignment:', assignmentEntry);
                    }
                });
            }
        });
        
        console.log('Final assignment data array:', assignmentData);
        
        // Validate we have assignments
        if (assignmentData.length === 0) {
            alert('No assignments selected. Please select at least one course for at least one member.');
            return;
        }
        
        // Count unique members with assignments
        const membersWithAssignments = Object.values(memberAssignments)
            .filter(a => a.passes && a.passes.length > 0).length;
        
        // Confirm with user
        if (!confirm(`Confirm assignment of ${assignmentData.length} passes to ${membersWithAssignments} members?`)) {
            return;
        }
        
        // Update button state
        const $confirmBtn = $('#confirm-multi-assignments-btn');
        $confirmBtn.prop('disabled', true).text('Processing...');
        
        // Prepare AJAX data
        const ajaxData = {
            action: 'llmsgaa_bulk_assign_multi_pass',
            assignments: JSON.stringify(assignmentData),
            group_id: group_id,
            nonce: nonce
        };
        
        console.log('=== Sending AJAX request ===');
        console.log('URL:', window.ajaxurl || window.llmsgaa_bulk?.ajax_url);
        console.log('Data being sent:', ajaxData);
        console.log('Sample assignment:', assignmentData[0]);
        
        // Send AJAX request
        jQuery.ajax({
            url: window.ajaxurl || window.llmsgaa_bulk?.ajax_url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                console.log('AJAX Success Response:', response);
                
                if (response.success) {
                    // Close modal
                    closeEnhancedModal();
                    
                    // Show success message
                    const message = response.data?.message || 
                        `Successfully assigned ${response.data?.assigned_count || assignmentData.length} passes!`;
                    alert(message);
                    
                    // Reload page to show updated assignments
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    // Handle error response
                    const errorMsg = response.data?.message || response.data || 'Unknown error occurred';
                    console.error('Assignment failed:', errorMsg);
                    alert('Error: ' + errorMsg);
                    
                    // Re-enable button
                    $confirmBtn.prop('disabled', false).text('✅ Confirm Assignments');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AJAX Error ===');
                console.error('Status:', xhr.status);
                console.error('Status Text:', xhr.statusText);
                console.error('Response:', xhr.responseText);
                console.error('Error:', error);
                
                // Parse error message
                let errorMsg = 'Server error occurred. Please try again.';
                
                try {
                    if (xhr.responseText) {
                        // Check for common WordPress errors
                        if (xhr.responseText === '0' || xhr.responseText.includes('0</')) {
                            errorMsg = 'Action not found or nonce verification failed. Please refresh and try again.';
                        } else if (xhr.responseText.includes('Fatal error')) {
                            errorMsg = 'PHP error occurred. Please check server logs.';
                        } else if (xhr.responseText.includes('{"success":false')) {
                            // Try to parse JSON error
                            const parsed = JSON.parse(xhr.responseText);
                            errorMsg = parsed.data || 'Request failed.';
                        }
                    }
                } catch(e) {
                    console.error('Error parsing response:', e);
                }
                
                alert(errorMsg + '\n\nCheck browser console for details.');
                
                // Re-enable button
                $confirmBtn.prop('disabled', false).text('✅ Confirm Assignments');
            }
        });
    };
    
    // Global helper functions
    window.closeEnhancedModal = function() {
        $('#enhanced-bulk-modal').remove();
        // Clear the stored data
        delete window.bulkAssignData;
    };
    
    // Override existing bulk assign button
    $(document).off('click', '#llmsgaa-bulk-assign-btn');
    $(document).on('click', '#llmsgaa-bulk-assign-btn', function(e) {
        e.preventDefault();
        
        const selectedEmails = $('.member-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selectedEmails.length === 0) {
            alert('Please select at least one member.');
            return;
        }
        
        showEnhancedBulkAssignModal(selectedEmails);
    });
});