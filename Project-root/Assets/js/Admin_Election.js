document.addEventListener('DOMContentLoaded', function() {
    const electionTableBody = document.getElementById('election-table-body');
    const electionTypeFilter = document.getElementById('electionTypeFilter');
    const electionStatusFilter = document.getElementById('electionStatusFilter');
    const noElectionsMessageContainer = document.getElementById('no-elections-message-container');
    const noElectionsMessage = document.getElementById('no-elections-message');
    const noteSection = document.getElementById('note-section');
    const tableHeaders = document.querySelectorAll('th[data-column]');

    // Get references to custom dialog elements (for delete confirmation)
    const confirmOverlay = document.getElementById('custom-confirm-overlay');
    const confirmDialog = document.getElementById('custom-confirm-dialog');
    const confirmMessage = document.getElementById('confirm-message');
    const confirmYesBtn = document.getElementById('confirm-yes');
    const confirmNoBtn = document.getElementById('confirm-no');

    // Notification element
    const notification = document.getElementById('notification');

    // Variables to store current deletion context
    let currentDeleteButton = null;
    let currentPollId = null;

    let currentSortColumn = 'poll_id'; // Default sort column
    let currentSortDirection = 'asc'; // Default sort direction

    // Pagination elements
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const currentPageSpan = document.getElementById('currentPage');
    const totalPagesSpan = document.getElementById('totalPages');

    let currentPage = 1;
    const itemsPerPage = 5; // You can make this configurable

    // Search elements
    const electionSearchInput = document.getElementById('electionSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');

    // Candidates Modal elements
    const candidatesModal = document.getElementById('candidatesModal');
    const candidatesContent = document.getElementById('candidatesContent');
    const closeModalBtn = candidatesModal.querySelector('.close-button');


    // Helper function to show notifications
    function showNotification(message, isError = false) {
        notification.textContent = message;
        notification.className = isError ? 'notification error' : 'notification';
        notification.style.display = 'block';

        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000);
    }

    // Function to render the elections table dynamically via AJAX
    async function fetchAndRenderElections() {
        electionTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">Loading elections...</td></tr>';
        noElectionsMessageContainer.style.display = 'none';
        noteSection.style.display = 'none';

        const searchQuery = electionSearchInput.value.trim();
        const electionType = electionTypeFilter.value;
        const electionStatus = electionStatusFilter.value;

        // Construct URL parameters
        const params = new URLSearchParams({
            fetch_table_data: true, // Indicate this is a request for table data
            page: currentPage,
            limit: itemsPerPage,
            sort_column: currentSortColumn,
            sort_direction: currentSortDirection,
            search_query: searchQuery,
            election_type_filter: electionType,
            election_status_filter: electionStatus // This will need to be handled PHP side for status calculation
        });

        try {
            const response = await fetch(`${window.location.href}?${params.toString()}`);
            const result = await response.json();

            if (result.success) {
                const elections = result.elections;
                const totalPages = result.total_pages;
                const totalRecords = result.total_records;

                electionTableBody.innerHTML = ''; // Clear previous content

                if (elections.length > 0) {
                    elections.forEach(row => {
                        // Use the status already calculated by PHP
                        const display_status = row.status; 

                        const tr = document.createElement('tr');
                        tr.id = `election-${row.poll_id}`;
                        tr.dataset.electionName = row.election_name;
                        tr.dataset.electionType = row.election_type;
                        tr.dataset.status = display_status; // Use calculated status
                        tr.dataset.startDatetime = row.start_datetime;
                        tr.dataset.endDatetime = row.end_datetime;
                        tr.dataset.candidateCount = row.candidate_count;

                        tr.innerHTML = `
                            <td>${row.election_name}</td>
                            <td>${row.election_type}</td>
                            <td>${display_status}</td>
                            <td>${row.start_datetime}</td>
                            <td>${row.end_datetime}</td>
                            <td>${row.candidate_count}</td>
                            <td class="action-buttons">
                                <button type="button" class="edit-election-btn tooltip"
                                    data-poll-id="${row.poll_id}"
                                    data-tooltip="Edit ${row.election_name} ${row.poll_id}">✏️</button>
                                <a href="#"
                                    class="delete-button tooltip"
                                    data-poll-id="${row.poll_id}" data-tooltip="Delete ${row.election_name} ${row.poll_id}">🗑️</a>
                                <button type="button"
                                    class="view-candidates-btn tooltip"
                                    data-poll-id="${row.poll_id}"
                                    data-tooltip="View Candidates for ${row.election_name}">👥</button>
                            </td>
                        `;
                        electionTableBody.appendChild(tr);
                    });
                    noElectionsMessageContainer.style.display = 'none';
                    noteSection.style.display = 'block';
                } else {
                    noElectionsMessage.textContent = searchQuery || electionType || electionStatus ? 
                    "No matching elections found for the selected criteria." : "There are no current existing elections.";
                    noElectionsMessageContainer.style.display = 'block';
                    noteSection.style.display = 'none';
                }

                // Update pagination controls
                currentPageSpan.textContent = result.current_page; // Use the page returned by PHP
                totalPagesSpan.textContent = totalPages;
                prevPageBtn.disabled = (currentPage === 1);
                nextPageBtn.disabled = (currentPage === totalPages || totalPages === 0);

            } else {
                electionTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color:red;">
                Error loading elections: ${result.message}</td></tr>`;
                showNotification('Error loading elections: ' + result.message, true);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            electionTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color:red;">An unexpected error occurred: ${error.message}</td></tr>`;
            showNotification('An unexpected error occurred: ' + error.message, true);
        }
    }


    // Event listeners for the filter dropdowns
    electionTypeFilter.addEventListener('change', () => {
        currentPage = 1; // Reset to first page on filter change
        fetchAndRenderElections();
    });
    electionStatusFilter.addEventListener('change', () => {
        currentPage = 1; // Reset to first page on filter change
        fetchAndRenderElections();
    });

    // Event listeners for sorting (delegated to the parent table to handle clicks on th)
    document.querySelector('thead tr').addEventListener('click', function(event) {
        const targetTh = event.target.closest('th[data-column]');

        // Prevent sorting if click is directly on a select element within a th
        if (!targetTh || event.target.tagName === 'SELECT') {
            return;
        }

        const column = targetTh.dataset.column;

        // Update sort direction
        if (currentSortColumn === column) {
            currentSortDirection = (currentSortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            currentSortColumn = column;
            currentSortDirection = 'asc'; // Default to ascending for new column
        }

        // Remove existing sort classes and arrows from all headers
        tableHeaders.forEach(th => {
            th.classList.remove('asc', 'desc');
        });

        // Add new sort class to the clicked header
        targetTh.classList.add(currentSortDirection);

        currentPage = 1; // Reset to first page on sort change
        fetchAndRenderElections(); // Re-fetch both filter and sort
    });


    // Delegated event listener for delete buttons
    electionTableBody.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-button');
        if (!deleteButton) return;

        event.preventDefault();

        currentDeleteButton = deleteButton;
        currentPollId = deleteButton.dataset.pollId;

        // Show custom confirmation dialog
        confirmMessage.textContent = 'Are you sure you want to delete this election? This action cannot be undone.';
        confirmDialog.style.display = 'block';
        confirmOverlay.style.display = 'flex'; // Use flex to center
    });

    // Handle "Yes" click on custom dialog
    confirmYesBtn.addEventListener('click', async function() {
        // Hide dialog
        confirmDialog.style.display = 'none';
        confirmOverlay.style.display = 'none';

        // Proceed with the actual delete
        if (currentDeleteButton && currentPollId) {
            const originalButtonText = currentDeleteButton.textContent;
            const originalTooltip = currentDeleteButton.dataset.tooltip;

            // Visual feedback: Change button state
            currentDeleteButton.textContent = 'Deleting...';
            currentDeleteButton.classList.add('loading');
            currentDeleteButton.style.pointerEvents = 'none';
            currentDeleteButton.dataset.tooltip = 'Deleting election...';

            try {
                const formData = new URLSearchParams();
                formData.append('delete_poll_id', currentPollId);

                const response = await fetch(window.location.href, { // Pointing to itself
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message); // Show success notification
                    fetchAndRenderElections(); // Re-fetch to update table
                } else {
                    // Start of the new logic to handle the specific error
                    let errorMessage = 'Error: ' + data.message;
                    if (data.message.includes('foreign key constraint fails')) {
                        errorMessage = 'Error: You cannot delete this election because it has associated votes. Please ensure all ballots and candidates are removed before attempting to delete the election.';
                    }
                    showNotification(errorMessage, true); // Show the more user-friendly error
                    // End of the new logic
                    
                    // Restore button state on error
                    currentDeleteButton.textContent = originalButtonText;
                    currentDeleteButton.classList.remove('loading');
                    currentDeleteButton.style.pointerEvents = 'auto';
                    currentDeleteButton.dataset.tooltip = originalTooltip;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showNotification('An unexpected error occurred: ' + error.message, true); // Show error notification
                // Restore button state on network/parsing error
                currentDeleteButton.textContent = originalButtonText;
                currentDeleteButton.classList.remove('loading');
                currentDeleteButton.style.pointerEvents = 'auto';
                currentDeleteButton.dataset.tooltip = originalTooltip;
            }
        }
    });

    // Handle "No" click on custom dialog or clicking outside the dialog
    confirmNoBtn.addEventListener('click', function() {
        confirmDialog.style.display = 'none';
        confirmOverlay.style.display = 'none';
        currentDeleteButton = null;
        currentPollId = null;
    });

    confirmOverlay.addEventListener('click', function(event) {
        if (event.target === confirmOverlay) { // Only close if clicking on the overlay itself, not the dialog
            confirmDialog.style.display = 'none';
            confirmOverlay.style.display = 'none';
            currentDeleteButton = null;
            currentPollId = null;
        }
    });

    // --- Pagination Event Listeners ---
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchAndRenderElections();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        // totalPagesSpan.textContent holds the current total pages from the last fetch
        if (currentPage < parseInt(totalPagesSpan.textContent)) {
            currentPage++;
            fetchAndRenderElections();
        }
    });

    // --- Search Event Listeners ---
    electionSearchInput.addEventListener('input', () => {
        currentPage = 1; // Reset to first page on search
        fetchAndRenderElections();
    });

    clearSearchBtn.addEventListener('click', () => {
        electionSearchInput.value = ''; // Clear search input
        currentPage = 1; // Reset to first page
        fetchAndRenderElections();
    });

    // --- View Candidates Modal Logic ---
    electionTableBody.addEventListener('click', async function(event) {
        const viewCandidatesBtn = event.target.closest('.view-candidates-btn');
        if (!viewCandidatesBtn) return;

        event.preventDefault();
        const pollIdToView = viewCandidatesBtn.dataset.pollId;

        try {
            // Use the existing fetch_poll_id endpoint, but specifically for viewing candidates
            const response = await fetch(`${window.location.href}?view_candidates_poll_id=${pollIdToView}`);
            const result = await response.json();

            if (result.success) {
                const election = result.election;
                const candidates = result.candidates;

                let candidatesHtml = `<h3>Candidates for "${election.election_name}"</h3>`;
                if (candidates.length > 0) {
                    candidatesHtml += '<ul>';
                    candidates.forEach(candidate => {
                        candidatesHtml += `
                            <li>
                                <span><strong>${candidate.candidate_name}</strong></span>
                                <span>(Party: ${candidate.party || 'N/A'})</span>
                            </li>`;
                    });
                    candidatesHtml += '</ul>';
                } else {
                    candidatesHtml += '<p style="text-align: center; color: #777;">No candidates found for this election.</p>';
                }
                candidatesContent.innerHTML = candidatesHtml;
                candidatesModal.style.display = 'flex'; // Use flex to center the modal
            } else {
                showNotification('Error loading candidates: ' + result.message, true);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showNotification('An unexpected error occurred while loading candidates: ' + error.message, true);
        }
    });

    // Close candidates modal when close button is clicked
    closeModalBtn.addEventListener('click', () => {
        candidatesModal.style.display = 'none';
    });

    // Close candidates modal when clicking outside the modal content
    candidatesModal.addEventListener('click', (event) => {
        if (event.target === candidatesModal) {
            candidatesModal.style.display = 'none';
        }
    });

    // --- NEW ELECTION WIZARD LOGIC ---
    const triggerNewElectionWizardBtn = document.getElementById('triggerNewElectionWizardBtn');
    const newElectionWizardOverlay = document.getElementById('newElectionWizardOverlay');
    const newWizardSteps = document.querySelectorAll('.new-wizard-step');
    const newPrevBtn = document.querySelector('.new-prev-btn');
    const newNextBtn = document.querySelector('.new-next-btn');
    const newSubmitBtn = document.querySelector('.new-submit-btn');
    const newWizardHeader = newElectionWizardOverlay.querySelector('.new-wizard-header h2');

    // Step 1 Elements
    const newElectionNameInput = document.getElementById('newElectionName');
    const newElectionTypeSelect = document.getElementById('newElectionType');
    const newStartDatetimeInput = document.getElementById('newStartDatetime');
    const newEndDatetimeInput = document.getElementById('newEndDatetime');
    const editPollIdInput = document.getElementById('editPollId'); // Hidden input for edit mode

    // Step 2 Elements
    const newCandidateNameInput = document.getElementById('newCandidateName');
    const newCandidatePartyInput = document.getElementById('newCandidateParty');
    const newAddCandidateBtn = document.getElementById('new-add-candidate-btn');
    const newCandidatesListDiv = document.getElementById('new-candidates-list');
    const newNoCandidatesMessage = document.getElementById('new-no-candidates-message');
    const candidateAddConfirmation = document.getElementById('candidateAddConfirmation'); // New: Confirmation message element

    let newCurrentStep = 0;
    let newCandidatesData = []; // Array to store candidate objects for the new wizard
    let editingCandidateIndex = null; // New: To store the index of the candidate being edited


    // Step 3 Elements
    const newReviewElectionName = document.getElementById('newReviewElectionName');
    const newReviewElectionType = document.getElementById('newReviewElectionType');
    const newReviewStartDatetime = document.getElementById('newReviewStartDatetime');
    const newReviewEndDatetime = document.getElementById('newReviewEndDatetime');
    const newReviewCandidatesList = document.getElementById('new-review-candidates-list');


    // Function to update new wizard display
    function updateNewWizardDisplay() {
        newWizardSteps.forEach((step, index) => {
            step.classList.toggle('active', index === newCurrentStep);
        });

        // Handle button visibility and text
        newPrevBtn.style.display = newCurrentStep === 0 ? 'none' : 'inline-block';
        newNextBtn.style.display = newCurrentStep === newWizardSteps.length - 1 ? 'none' : 'inline-block';
        newSubmitBtn.style.display = newCurrentStep === newWizardSteps.length - 1 ? 'inline-block' : 'none';

        if (editPollIdInput.value) { // If in edit mode
            newSubmitBtn.textContent = 'Update Election';
            newWizardHeader.textContent = 'Edit Election';
        } else {
            newSubmitBtn.textContent = 'Submit Election';
            newWizardHeader.textContent = 'Create New Election';
        }

        // Populate review section if on the last step
        if (newCurrentStep === newWizardSteps.length - 1) {
            populateNewReview();
        }
    }

    // Function to populate the new review section
    function populateNewReview() {
        newReviewElectionName.textContent = newElectionNameInput.value || 'N/A';
        newReviewElectionType.textContent = newElectionTypeSelect.value || 'N/A';
        newReviewStartDatetime.textContent = newStartDatetimeInput.value ? new Date(newStartDatetimeInput.value).toLocaleString() : 'N/A';
        newReviewEndDatetime.textContent = newEndDatetimeInput.value ? new Date(newEndDatetimeInput.value).toLocaleString() : 'N/A';

        newReviewCandidatesList.innerHTML = '';
        if (newCandidatesData.length > 0) {
            newCandidatesData.forEach(candidate => {
                const listItem = document.createElement('li');
                listItem.textContent = `${candidate.name} (Party: ${candidate.party || 'N/A'})`;
                newReviewCandidatesList.appendChild(listItem);
            });
        } else {
            const listItem = document.createElement('li');
            listItem.textContent = 'No candidates added.';
            newReviewCandidatesList.appendChild(listItem);
        }
    }

    // Function to add or update a candidate in the newCandidatesData array and update the UI
    function addNewCandidate() {
        const name = newCandidateNameInput.value.trim();
        const party = newCandidatePartyInput.value.trim();

        if (name) {
            if (editingCandidateIndex !== null) {
                // Update existing candidate
                newCandidatesData[editingCandidateIndex] = { name, party };
            } else {
                // Add new candidate
                newCandidatesData.push({ name, party });
            }
            renderNewCandidatesList(); // Re-render the list
            
            // Show confirmation message
            candidateAddConfirmation.textContent = editingCandidateIndex !== null ? 'Candidate Updated!' : 'Candidate Added!';
            candidateAddConfirmation.style.display = 'block';
            setTimeout(() => {
                candidateAddConfirmation.style.display = 'none';
            }, 2000); // Hide after 2 seconds

            // Clear input fields and reset editing state
            newCandidateNameInput.value = '';
            newCandidatePartyInput.value = '';
            editingCandidateIndex = null; // Reset editing index
            updateAddCandidateButtonText(); // Update button text
        } else {
            showNotification('Candidate Name is required.', true);
        }
    }

    // New function: Populate candidate input fields for editing
    function editNewCandidate(index) {
        const candidateToEdit = newCandidatesData[index];
        newCandidateNameInput.value = candidateToEdit.name;
        newCandidatePartyInput.value = candidateToEdit.party;
        editingCandidateIndex = index; // Set the index of the candidate being edited
        updateAddCandidateButtonText(); // Change button text to "Update Candidate"
    }


    // Function to render the list of candidates in the new UI
    function renderNewCandidatesList() {
        newCandidatesListDiv.innerHTML = ''; // Clear existing list
        if (newCandidatesData.length === 0) {
            newNoCandidatesMessage.style.display = 'block';
        } else {
            newNoCandidatesMessage.style.display = 'none';
            newCandidatesData.forEach((candidate, index) => {
                const candidateItem = document.createElement('div');
                candidateItem.classList.add('new-candidate-item'); // Use new class
                candidateItem.innerHTML = `
                    <span class="new-candidate-info">
                        <strong>${candidate.name}</strong> 
                        ${candidate.party ? `(Party: ${candidate.party})` : ''}
                    </span>
                    <div class="new-candidate-actions">
                        <button type="button" data-index="${index}" class="edit-candidate-btn">✏️</button>
                        <button type="button" data-index="${index}" class="new-remove-candidate-btn">🗑️</button>
                    </div>
                `;
                newCandidatesListDiv.appendChild(candidateItem);
            });
        }
    }

    // Function to remove a candidate from the new wizard
    function removeNewCandidate(index) {
        newCandidatesData.splice(index, 1);
        renderNewCandidatesList(); // Re-render the list
        if (editingCandidateIndex === index) { // If the candidate being removed was the one being edited
            editingCandidateIndex = null; // Reset editing state
            newCandidateNameInput.value = ''; // Clear inputs
            newCandidatePartyInput.value = '';
            updateAddCandidateButtonText(); // Reset button text
        } else if (editingCandidateIndex > index) { // If a candidate before the edited one was removed
            editingCandidateIndex--; // Adjust the editing index
        }
    }

    // New function: Update the text of the "Add Candidate" button
    function updateAddCandidateButtonText() {
        if (editingCandidateIndex !== null) {
            newAddCandidateBtn.textContent = 'Update Candidate';
        } else {
            newAddCandidateBtn.textContent = 'Add Candidate';
        }
    }


    // Reset wizard state for new creation
    function resetNewWizardForCreate() {
        editPollIdInput.value = ''; // Clear poll_id for new creation
        newElectionNameInput.value = '';
        newElectionTypeSelect.value = '';
        newStartDatetimeInput.value = '';
        newEndDatetimeInput.value = '';
        newCandidateNameInput.value = '';
        newCandidatePartyInput.value = '';
        newCandidatesData = [];
        editingCandidateIndex = null; // Ensure editing state is reset
        renderNewCandidatesList();
        newCurrentStep = 0;
        updateNewWizardDisplay();
        updateAddCandidateButtonText(); // Update candidate button text
        candidateAddConfirmation.style.display = 'none'; // Hide confirmation on reset
    }

    // Event listener for "Add New Election" button (trigger for the new wizard)
    triggerNewElectionWizardBtn.addEventListener('click', function() {
        resetNewWizardForCreate(); // Reset for new creation
        newElectionWizardOverlay.style.display = 'flex'; // Show the new wizard
    });

    // Event listener for "Edit" buttons in the table (delegated)
    electionTableBody.addEventListener('click', async function(event) {
        const editButton = event.target.closest('.edit-election-btn');
        if (!editButton) return;

        event.preventDefault();
        const pollIdToEdit = editButton.dataset.pollId;

        // Reset wizard first in case it was used for creation
        resetNewWizardForCreate();

        try {
            // Fetch existing election and candidate data
            const response = await fetch(`${window.location.href}?fetch_poll_id=${pollIdToEdit}`);
            const result = await response.json();

            if (result.success) {
                // Corrected: Access election and candidates directly from result
                const election = result.election;
                const candidates = result.candidates;

                // Populate form fields
                editPollIdInput.value = election.poll_id;
                newElectionNameInput.value = election.election_name;
                newElectionTypeSelect.value = election.election_type;
                
                // Format datetime-local input
                newStartDatetimeInput.value = election.start_datetime ? new Date(election.start_datetime).toISOString().slice(0, 16) : '';
                newEndDatetimeInput.value = election.end_datetime ? new Date(election.end_datetime).toISOString().slice(0, 16) : '';

                // Populate candidates
                newCandidatesData = candidates.map(c => ({
                    name: c.candidate_name,
                    party: c.party
                }));
                renderNewCandidatesList();

                // Open wizard in edit mode
                newElectionWizardOverlay.style.display = 'flex';
                newCurrentStep = 0; // Start at first step
                updateNewWizardDisplay(); // Update display to show "Edit Election"
            } else {
                showNotification('Error loading election data: ' + result.message, true);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showNotification('An unexpected error occurred while loading election data: ' + error.message, true);
        }
    });


    // Event listener for New Wizard Next button
    newNextBtn.addEventListener('click', function() {
        // Validate current step before moving next
        if (newCurrentStep === 0) { // Election Details validation
            if (!newElectionNameInput.value.trim() || !newElectionTypeSelect.value || !newStartDatetimeInput.value) {
                showNotification('Please fill in all required election details.', true);
                return;
            }
             // Validate dates
            const startDate = new Date(newStartDatetimeInput.value);
            const endDate = newEndDatetimeInput.value ? new Date(newEndDatetimeInput.value) : null;
            
            if (endDate && endDate < startDate) {
                showNotification('End Date & Time cannot be before Start Date & Time.', true);
                return;
            }

        } else if (newCurrentStep === 1) { // Candidate Details validation
            if (newCandidatesData.length === 0) {
                showNotification('Please add at least one candidate.', true);
                return;
            }
        }

        if (newCurrentStep < newWizardSteps.length - 1) {
            newCurrentStep++;
            updateNewWizardDisplay();
        }
    });

    // Event listener for New Wizard Previous button
    newPrevBtn.addEventListener('click', function() {
        if (newCurrentStep > 0) {
            newCurrentStep--;
            updateNewWizardDisplay();
        }
    });

    // Event listener for New Wizard Add Candidate button
    newAddCandidateBtn.addEventListener('click', addNewCandidate);

    // Delegated event listener for remove candidate buttons in new wizard
    newCandidatesListDiv.addEventListener('click', function(event) {
        if (event.target.classList.contains('new-remove-candidate-btn')) { // Use new class for remove
            const indexToRemove = parseInt(event.target.dataset.index);
            removeNewCandidate(indexToRemove);
        } else if (event.target.classList.contains('edit-candidate-btn')) { // New: Edit button
            const indexToEdit = parseInt(event.target.dataset.index);
            editNewCandidate(indexToEdit);
        }
    });

    // Event listener for New Wizard Submit/Update button
    newSubmitBtn.addEventListener('click', async function() {
        // Collect all data
        const electionDetails = {
            poll_id: editPollIdInput.value || null, // Will be null for new creation
            election_name: newElectionNameInput.value,
            election_type: newElectionTypeSelect.value,
            start_datetime: newStartDatetimeInput.value,
            end_datetime: newEndDatetimeInput.value
        };

        // Ensure candidatesData is clean (no extra UI properties if any were added)
        const candidatesToSend = newCandidatesData.map(c => ({
            name: c.name,
            party: c.party
        }));

        try {
            // Send AJAX request to the same Admin_Election.php file
            const response = await fetch(window.location.href, { // Pointing to itself
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    election: electionDetails,
                    candidates: candidatesToSend
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification(result.message);
                newElectionWizardOverlay.style.display = 'none'; // Close wizard
                fetchAndRenderElections(); // Re-fetch to update the table
            } else {
                showNotification('Error: ' + result.message, true);
            }
        } catch (error) {
            console.error('Submission error:', error);
            showNotification('An unexpected error occurred during submission: ' + error.message, true);
        }
    });

    // Close new wizard if clicking outside the container (on the overlay)
    newElectionWizardOverlay.addEventListener('click', function(event) {
        if (event.target === newElectionWizardOverlay) {
            newElectionWizardOverlay.style.display = 'none';
            resetNewWizardForCreate(); // Reset state when closing via overlay click
        }
    });

    // Initial fetch of elections when the page loads
    fetchAndRenderElections();
});

// Show/hide the dropdown and populate with available elections
document.getElementById('importCandidatesBtn').onclick = function() {
    const dropdown = document.getElementById('importCandidatesDropdownContainer');
    dropdown.style.display = 'block';

    const select = document.getElementById('importSourceElection');
    // Clear previous options except the default one
    select.innerHTML = '<option value="">-- Choose Election --</option>';

    // Get current poll ID (if editing)
    const destPollId = document.getElementById('editPollId').value || '';

    // Fetch elections, excluding the current one if editing
    fetch('Admin_Election.php?fetch_all_elections=1' + (destPollId ? '&exclude_poll_id=' + destPollId : ''), {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.elections && data.elections.length) {
            data.elections.forEach(election => {
                const option = document.createElement('option');
                option.value = election.poll_id;
                option.textContent = election.election_name + ' (ID: ' + election.poll_id + ')';
                select.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No elections available';
            select.appendChild(option);
        }
    });
};

// When confirm import is clicked
document.getElementById('confirmImportCandidatesBtn').onclick = function() {
    const sourcePollId = document.getElementById('importSourceElection').value;
    const destPollId = document.getElementById('editPollId').value || '';

    if (!sourcePollId || !destPollId) {
        alert('Please select a source election and ensure current election is being edited.');
        return;
    }

    const formData = new FormData();
    formData.append('import_candidates', '1');
    formData.append('dest_poll_id', destPollId);
    formData.append('source_poll_id', sourcePollId);

    fetch('Admin_Election.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Candidates imported!');
            refreshCurrentCandidates(destPollId);
        } else {
            alert('Import failed: ' + data.message);
        }
        document.getElementById('importCandidatesDropdownContainer').style.display = 'none';
    });
};

// Function to refresh the current candidates box after import
function refreshCurrentCandidates(pollId) {
    fetch('Admin_Election.php?fetch_poll_id=' + pollId, {
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        const candidateListDiv = document.getElementById('new-candidates-list');
        candidateListDiv.innerHTML = '';
        if (data.success && data.candidates && data.candidates.length > 0) {
            data.candidates.forEach(cand => {
                const p = document.createElement('p');
                p.textContent = cand.candidate_name + (cand.party ? ' (' + cand.party + ')' : '');
                candidateListDiv.appendChild(p);
            });
        } else {
            candidateListDiv.innerHTML = '<p id="new-no-candidates-message" style="text-align: center; color: #777;">No candidates added yet.</p>';
        }
    });
}