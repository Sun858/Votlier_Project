// NOTE: The PHP session check and includes at the top are assumed to be handled correctly on the server side.
// This JavaScript focuses on the client-side logic.

const API_URL = '../includes/api.php'
const docForm = document.getElementById('doc-form')
const docIdInput = document.getElementById('doc-id')
const docTitleInput = document.getElementById('doc-title')
const docContentTextarea = document.getElementById('doc-content')
const docCategorySelect = document.getElementById('doc-category')
const newCategoryInput = document.getElementById('new-category')
const documentsTableBody = document.querySelector('#documents-table tbody')
const saveBtn = document.getElementById('save-btn')
const cancelBtn = document.getElementById('cancel-btn')
const deleteBtn = document.getElementById('delete-btn')
const messageArea = document.getElementById('message-area')

// Modal elements
const deleteModal = document.getElementById('delete-modal')
const confirmDeleteBtn = document.getElementById('confirm-delete-btn')
const cancelDeleteBtn = document.getElementById('cancel-delete-btn')

let isEditing = false
let categories = []
let docToDeleteId = null // Variable to store the ID of the document to be deleted

// Function to display a message to the user
function showMessage (message, type) {
  messageArea.textContent = message
  messageArea.className = `alert-message alert-${type}`
  messageArea.style.display = 'block'
  setTimeout(() => {
    messageArea.style.display = 'none'
  }, 3000)
}

// Function to fetch and populate categories for the main form
const fetchCategories = async () => {
  try {
    const response = await fetch(`${API_URL}?action=getCategories`)
    categories = await response.json()

    docCategorySelect.innerHTML =
      '<option value="">-- Select a Category --</option>'
    categories.forEach(cat => {
      const option = document.createElement('option')
      option.value = cat.category_id
      option.textContent = cat.category_name
      docCategorySelect.appendChild(option)
    })
  } catch (error) {
    showMessage('Failed to fetch categories.', 'error')
    console.error('Error fetching categories:', error)
  }
}

// Function to fetch and populate documents table
const fetchDocuments = async () => {
  documentsTableBody.innerHTML =
    '<tr><td colspan="3">Loading documents...</td></tr>'
  try {
    const response = await fetch(`${API_URL}?action=getDocuments`)
    const documents = await response.json()

    documentsTableBody.innerHTML = ''
    if (documents.length > 0) {
      documents.forEach(doc => {
        const row = document.createElement('tr')
        row.innerHTML = `
                            <td>${doc.title}</td>
                            <td>${doc.category_name}</td>
                            <td>
                                <button class="btn btn-primary btn-sm edit-btn" data-id="${doc.document_id}">Edit</button>
                                <button class="btn btn-danger btn-sm delete-btn" data-id="${doc.document_id}">Delete</button>
                            </td>
                        `
        documentsTableBody.appendChild(row)
      })

      // Add event listeners for edit and delete buttons
      document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          editDocument(btn.dataset.id)
        })
      })
      document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', e => {
          // Show the custom modal instead of using confirm()
          e.preventDefault()
          docToDeleteId = btn.dataset.id
          deleteModal.style.display = 'flex'
        })
      })
    } else {
      documentsTableBody.innerHTML =
        '<tr><td colspan="3">No documents found.</td></tr>'
    }
  } catch (error) {
    showMessage('Failed to fetch documents.', 'error')
    console.error('Error fetching documents:', error)
  }
}

// Function to handle form submission (create or update)
docForm.addEventListener('submit', async e => {
  e.preventDefault()

  let category_id
  const newCategoryName = newCategoryInput.value.trim()

  if (newCategoryName) {
    // If a new category is entered, create it first
    try {
      const createCatResponse = await fetch(`${API_URL}?action=addCategory`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          category_name: newCategoryName
        })
      })
      const result = await createCatResponse.json()
      if (result.error) {
        showMessage(result.error, 'error')
        return
      }
      category_id = result.category_id
      await fetchCategories()
    } catch (error) {
      showMessage('Failed to create new category.', 'error')
      return
    }
  } else {
    category_id = docCategorySelect.value
  }

  const documentData = {
    title: docTitleInput.value,
    content: docContentTextarea.value,
    category_id: category_id
  }

  const action = isEditing ? 'updateDocument' : 'addDocument'
  if (isEditing) {
    documentData.document_id = docIdInput.value
  }

  try {
    const response = await fetch(`${API_URL}?action=${action}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(documentData)
    })
    const result = await response.json()

    if (result.error) {
      showMessage(result.error, 'error')
    } else {
      showMessage(
        `Document ${isEditing ? 'updated' : 'added'} successfully!`,
        'success'
      )
      resetForm()
      fetchDocuments()
    }
  } catch (error) {
    showMessage(`Failed to ${isEditing ? 'update' : 'add'} document.`, 'error')
    console.error('Error:', error)
  }
})

// Function to load a document into the form for editing
const editDocument = async id => {
  isEditing = true
  saveBtn.textContent = 'Update Document'
  deleteBtn.style.display = 'inline-block'

  try {
    const response = await fetch(
      `${API_URL}?action=getDocument&document_id=${id}`
    )
    const doc = await response.json()

    docIdInput.value = doc.document_id
    docTitleInput.value = doc.title
    docContentTextarea.value = doc.content
    docCategorySelect.value = doc.category_id
    newCategoryInput.value = ''
  } catch (error) {
    showMessage('Failed to load document for editing.', 'error')
    console.error('Error fetching document:', error)
  }
}

// Function to delete a document (now with modal confirmation)
const deleteDocument = async id => {
  try {
    const response = await fetch(
      `${API_URL}?action=deleteDocument&document_id=${id}`,
      {
        method: 'POST'
      }
    )
    const result = await response.json()

    if (result.error) {
      showMessage(result.error, 'error')
    } else {
      showMessage('Document deleted successfully!', 'success')
      resetForm()
      fetchDocuments()
    }
  } catch (error) {
    showMessage('Failed to delete document.', 'error')
    console.error('Error:', error)
  }
}

// Function to reset the form
const resetForm = () => {
  docForm.reset()
  docIdInput.value = ''
  isEditing = false
  saveBtn.textContent = 'Save Document'
  deleteBtn.style.display = 'none'
}

// Event listeners for the new modal buttons
confirmDeleteBtn.addEventListener('click', () => {
  if (docToDeleteId) {
    deleteDocument(docToDeleteId)
    docToDeleteId = null // Clear the ID after deletion
  }
  deleteModal.style.display = 'none'
})

cancelDeleteBtn.addEventListener('click', () => {
  docToDeleteId = null // Clear the ID
  deleteModal.style.display = 'none'
})

cancelBtn.addEventListener('click', resetForm)
deleteBtn.addEventListener('click', e => {
  e.preventDefault()
  // Show the custom modal instead of using confirm()
  docToDeleteId = docIdInput.value
  deleteModal.style.display = 'flex'
})

// Initialize the page by fetching data
window.onload = () => {
  fetchCategories()
  fetchDocuments()
}
