/**
 * Centralized Error and Success Modal Handler
 * Provides consistent error and success message display across the application
 */

// Global variables for auto-close timers
let errorModalTimeout = null;
let successModalTimeout = null;

/**
 * Show error modal with custom title and message
 * @param {string} title - Error title (default: 'Error')
 * @param {string} message - Error message (default: 'An error occurred.')
 * @param {number} autoCloseDelay - Auto-close delay in milliseconds (default: 5000, 0 to disable)
 */
function showErrorModal(title = 'Error', message = 'An error occurred.', autoCloseDelay = 5000) {
    const modal = document.getElementById('errorModal');
    const titleEl = document.getElementById('errorModalTitle');
    const messageEl = document.getElementById('errorModalMessage');
    
    // Clear any existing timeout
    if (errorModalTimeout) {
        clearTimeout(errorModalTimeout);
        errorModalTimeout = null;
    }
    
    if (!modal) {
        console.error('Error modal not found. Make sure modalAlertHandling.php is included.');
        // Fallback to alert
        alert(title + ': ' + message);
        return;
    }
    
    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    // Auto-close after specified delay
    if (autoCloseDelay > 0) {
        errorModalTimeout = setTimeout(() => {
            closeErrorModal();
        }, autoCloseDelay);
    }
}

/**
 * Close error modal
 */
function closeErrorModal() {
    const modal = document.getElementById('errorModal');
    
    if (errorModalTimeout) {
        clearTimeout(errorModalTimeout);
        errorModalTimeout = null;
    }
    
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
}

/**
 * Show success modal with custom title and message
 * @param {string} title - Success title (default: 'Success')
 * @param {string} message - Success message (default: 'Operation completed successfully.')
 * @param {number} autoCloseDelay - Auto-close delay in milliseconds (default: 3000, 0 to disable)
 */
function showSuccessModal(title = 'Success', message = 'Operation completed successfully.', autoCloseDelay = 3000) {
    const modal = document.getElementById('successModal');
    const titleEl = document.getElementById('successModalTitle');
    const messageEl = document.getElementById('successModalMessage');
    
    // Clear any existing timeout
    if (successModalTimeout) {
        clearTimeout(successModalTimeout);
        successModalTimeout = null;
    }
    
    if (!modal) {
        console.error('Success modal not found. Make sure modalAlertHandling.php is included.');
        // Fallback to alert
        alert(title + ': ' + message);
        return;
    }
    
    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    // Auto-close after specified delay
    if (autoCloseDelay > 0) {
        successModalTimeout = setTimeout(() => {
            closeSuccessModal();
        }, autoCloseDelay);
    }
}

/**
 * Close success modal
 */
function closeSuccessModal() {
    const modal = document.getElementById('successModal');
    
    if (successModalTimeout) {
        clearTimeout(successModalTimeout);
        successModalTimeout = null;
    }
    
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
}

/**
 * Handle URL parameters for success/error messages
 * Checks for ?success= or ?error= parameters and displays appropriate modal
 * @param {Object} successMessages - Object mapping success codes to messages
 * @param {Object} errorMessages - Object mapping error codes to messages
 */
function handleURLParameters(successMessages = {}, errorMessages = {}) {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    const entity = urlParams.get('entity') || urlParams.get('name') || urlParams.get('item') || '';
    const formattedEntity = entity ? `'${entity}'` : 'the item';
    const supplementalMessage = urlParams.get('msg') || '';
    const additionalDetails = urlParams.get('details') || '';

    const successDefaults = {
        created: {
            title: 'Created Successfully',
            message: `Successfully created ${formattedEntity}.`
        },
        updated: {
            title: 'Updated Successfully',
            message: `${formattedEntity} has been updated.`
        },
        deleted: {
            title: 'Deleted Successfully',
            message: `${formattedEntity} has been removed from the list.`
        },
        saved: {
            title: 'Changes Saved',
            message: `All changes to ${formattedEntity} have been saved.`
        },
        submitted: {
            title: 'Submitted Successfully',
            message: `Your submission for ${formattedEntity} was received.`
        },
        restored: {
            title: 'Restored Successfully',
            message: `${formattedEntity} has been restored.`
        }
    };

    const errorDefaults = {
        missing_fields: 'Please fill in all required fields before continuing.',
        duplicate: `${formattedEntity} already exists. Please use unique information.`,
        invalid_id: 'We could not find that record. Please refresh and try again.',
        forbidden: 'You do not have permission to perform this action.',
        not_found: `${formattedEntity} could not be located.`,
        delete_failed: `We were unable to delete ${formattedEntity}. Please try again.`,
        update_failed: `We were unable to update ${formattedEntity}. Please try again.`,
        create_failed: `We were unable to create ${formattedEntity}. Please try again.`,
        validation: 'Some information is invalid. Please review the highlighted fields.',
        timeout: 'The request took too long. Please check your connection and try again.',
        server_error: 'Something went wrong on the server. Please try again shortly.',
        unauthorized: 'Your session has expired or you are not signed in.'
    };
    
    if (success) {
        let title = 'Success';
        let message = 'Operation completed successfully.';
        
        // Check if custom message exists
        let successMsg = successMessages[success];
        
        // Handle function-based messages
        if (typeof successMsg === 'function') {
            message = successMsg();
        } else if (successMsg) {
            message = successMsg;
        } else {
            // Handle specific success types with default messages
            if (successDefaults[success]) {
                title = successDefaults[success].title;
                message = successDefaults[success].message;
            }
        }

        if (!successMsg && supplementalMessage) {
            message += ` ${supplementalMessage}`;
        } else if (successMsg && supplementalMessage) {
            message += ` ${supplementalMessage}`;
        }

        if (additionalDetails) {
            message += ` Details: ${additionalDetails}`;
        }
        
        showSuccessModal(title, message);
        
        // Clean URL by removing the parameter
        const newUrl = window.location.pathname + (error ? '?error=' + error : '');
        window.history.replaceState({}, '', newUrl);
    }
    
    if (error) {
        let title = 'Error';
        let message = 'An error occurred.';
        
        // Handle specific error types with additional parameters
        const errorParams = {};
        urlParams.forEach((value, key) => {
            if (key !== 'error') {
                errorParams[key] = value;
            }
        });
        
        // Build detailed error message
        let errorMsg = errorMessages[error];
        
        // Handle function-based messages
        if (typeof errorMsg === 'function') {
            message = errorMsg();
        } else if (errorMsg) {
            message = errorMsg;
        } else {
            // Fallback to default messages
            if (errorDefaults[error]) {
                message = errorDefaults[error];
            }
        }
        
        // Append additional details if present
        if (errorParams.count) {
            message += ' (Count: ' + errorParams.count + ')';
        }

        if (supplementalMessage) {
            message += ` ${supplementalMessage}`;
        }

        if (additionalDetails) {
            message += ` Details: ${additionalDetails}`;
        }
        
        showErrorModal(title, message);
        
        // Clean URL by removing the parameter
        const newUrl = window.location.pathname + (success ? '?success=' + success : '');
        window.history.replaceState({}, '', newUrl);
    }
}

/**
 * Initialize modal handlers
 * Sets up keyboard listeners and auto-handles URL parameters
 */
function initModalHandlers(successMessages = {}, errorMessages = {}) {
    // Handle Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeErrorModal();
            closeSuccessModal();
        }
    });
    
    // Handle URL parameters on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            handleURLParameters(successMessages, errorMessages);
        });
    } else {
        handleURLParameters(successMessages, errorMessages);
    }
}

// Expose functions to global scope
window.showErrorModal = showErrorModal;
window.closeErrorModal = closeErrorModal;
window.showSuccessModal = showSuccessModal;
window.closeSuccessModal = closeSuccessModal;
window.handleURLParameters = handleURLParameters;
window.initModalHandlers = initModalHandlers;

