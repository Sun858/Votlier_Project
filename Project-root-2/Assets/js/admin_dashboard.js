// admin_dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    // Cache DOM elements for better performance
    const viewButtons = document.querySelectorAll('.view-candidates-btn');
    const modal = document.getElementById('candidatesModal');
    const closeButton = document.querySelector('.close-button');
    const candidatesContent = document.getElementById('candidatesContent');

    // Ensure all required elements exist before attaching listeners
    if (!modal || !candidatesContent) {
        console.error('Error: Missing essential DOM elements (modal or candidatesContent).');
        return; // Stop execution if critical elements are missing
    }

    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const pollId = this.dataset.pollId; // Get the poll_id from the data-poll-id attribute

            // Basic validation for pollId
            if (!pollId) {
                console.warn('Warning: No poll ID found for this button.');
                candidatesContent.innerHTML = '<p>Error: Could not retrieve poll ID. Please try again.</p>';
                modal.style.display = 'flex'; // Use 'flex' for centering when showing an error
                return;
            }

            candidatesContent.innerHTML = '<p>Loading candidates...</p>'; // Show a loading message
            modal.style.display = 'flex'; // CHANGED: Use 'flex' to display and center the modal

            // Make an AJAX request using async/await for cleaner syntax
            fetch(`../admin/view_candidates.php?poll_id=${pollId}`)
                .then(response => {
                    // Check for HTTP errors (e.g., 404, 500)
                    if (!response.ok) {
                        // Throw an error with the status for more specific debugging
                        throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
                    }
                    return response.text(); // Parse the response as text (HTML)
                })
                .then(html => {
                    candidatesContent.innerHTML = html; // Inject the fetched HTML
                })
                .catch(error => {
                    console.error('Error loading candidates:', error);
                    // Provide a user-friendly error message
                    candidatesContent.innerHTML = `<p>Error loading candidates. Please try again. (Details: ${error.message})</p>`;
                });
        });
    });

    // Close the modal when the close button is clicked
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            modal.style.display = 'none';
            // Clear content and reset to loading message for the next time it opens
            candidatesContent.innerHTML = '<p>Loading candidates...</p>';
        });
    } else {
        console.warn('Warning: Close button not found.');
    }

    // Close the modal if the user clicks anywhere outside of the modal content
    window.addEventListener('click', function(event) {
        if (event.target === modal) { // Use strict equality for comparison
            modal.style.display = 'none';
            // Clear content and reset to loading message for the next time it opens
            candidatesContent.innerHTML = '<p>Loading candidates...</p>';
        }
    });
});