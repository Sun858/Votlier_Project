// Function for social icon animation
function initSocialIcons () {
  const socialIcons = document.querySelectorAll('.social-links a')
  socialIcons.forEach((icon, index) => {
    icon.style.animationDelay = `${index * 0.1}s`
  })
}

// Function for accordion functionality
function initAccordions () {
  document.addEventListener('DOMContentLoaded', () => {
    const accordionButtons = document.querySelectorAll('.accordion-button')
    accordionButtons.forEach(button => {
      button.addEventListener('click', () => {
        const content = button.nextElementSibling
        const isActive = button.classList.toggle('active')
        content.classList.toggle('active')
        if (isActive) {
          content.style.maxHeight = content.scrollHeight + 'px'
        } else {
          content.style.maxHeight = '0'
        }
      })
    })
  })
}

//Function for documentation page
function documentationPage () {
  initSocialIcons() // Assuming this function is in Functions.js
  const API_URL = '../controllers/api.php'
  const categoryListEl = document.getElementById('category-list')
  const documentListEl = document.getElementById('document-list')
  const documentViewerEl = document.getElementById('document-viewer')

  // Function to fetch and display documents for a given category
  const renderDocumentsForCategory = async categoryId => {
    console.log('Fetching documents for category ID:', categoryId)
    documentListEl.innerHTML = '<h3>Documents</h3><p>Loading documents...</p>'
    documentViewerEl.innerHTML = '<p>Select a document to view its content.</p>'

    try {
      const response = await fetch(
        `${API_URL}?action=getPublicDocumentsByCategory&category_id=${categoryId}`
      )
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      const documents = await response.json()
      console.log('Parsed documents:', documents)

      if (documents.error) {
        documentListEl.innerHTML = `<p style="color: #ef4444;">Error: ${documents.error}</p>`
        return
      }

      if (documents.length > 0) {
        let documentLinksHtml = `<h3>Documents</h3><ul>`
        documents.forEach(doc => {
          documentLinksHtml += `<li><a href="#" class="document-link" data-id="${doc.document_id}">${doc.title}</a></li>`
        })
        documentLinksHtml += '</ul>'
        documentListEl.innerHTML = documentLinksHtml

        // Add event listeners to the new document links
        document.querySelectorAll('.document-link').forEach(link => {
          link.addEventListener('click', e => {
            e.preventDefault()
            renderDocument(e.target.dataset.id)
            // Add active class to the clicked link
            document
              .querySelectorAll('.document-link')
              .forEach(l => l.classList.remove('active'))
            e.target.classList.add('active')
          })
        })

        // Automatically render the first document and add active class
        renderDocument(documents[0].document_id)
        const firstDocumentLink = document.querySelector('.document-link')
        if (firstDocumentLink) {
          firstDocumentLink.classList.add('active')
        }
      } else {
        documentListEl.innerHTML =
          '<h3>Documents</h3><p>No documents found in this category.</p>'
      }
    } catch (error) {
      console.error('Error fetching documents:', error)
      documentListEl.innerHTML = `<p style="color: #ef4444;">Failed to load documents: ${error.message}</p>`
    }
  }

  // Function to fetch and display a single document's content
  const renderDocument = async id => {
    console.log('Fetching document with ID:', id)
    documentViewerEl.innerHTML = '<p>Loading document content...</p>'

    try {
      const response = await fetch(
        `${API_URL}?action=getPublicDocument&document_id=${id}`
      )
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      const document = await response.json()
      console.log('Parsed document:', document)
      if (document.error) {
        documentViewerEl.innerHTML = `<p style="color: #ef4444;">Error: ${document.error}</p>`
        return
      }
      const htmlContent = marked.parse(document.content)
      documentViewerEl.innerHTML = `
                    <div class="markdown-rendered">
                        <h1>${document.title}</h1>
                        ${DOMPurify.sanitize(htmlContent)}
                    </div>
                `
    } catch (error) {
      console.error('Error fetching document:', error)
      documentViewerEl.innerHTML =
        '<p style="color: #ef4444;">Failed to load document.</p>'
    }
  }

  // Initial function to load categories
  const fetchCategories = async () => {
    console.log('Fetching categories...')
    categoryListEl.innerHTML = '<h3>Categories</h3><ul><li>Loading...</li></ul>'
    try {
      const response = await fetch(`${API_URL}?action=getPublicCategories`)
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      const categories = await response.json()
      if (categories.error) {
        categoryListEl.innerHTML = `<p style="color: #ef4444;">Error: ${categories.error}</p>`
        return
      }

      if (categories.length > 0) {
        let listItems = `<h3>Categories</h3><ul>`
        categories.forEach(cat => {
          listItems += `<li><a href="#" class="category-link" data-id="${cat.category_id}">${cat.category_name}</a></li>`
        })
        listItems += '</ul>'
        categoryListEl.innerHTML = listItems

        // Add event listeners for the new category links
        document.querySelectorAll('.category-link').forEach(link => {
          link.addEventListener('click', e => {
            e.preventDefault()
            renderDocumentsForCategory(e.target.dataset.id)
            document
              .querySelectorAll('.category-link')
              .forEach(l => l.classList.remove('active'))
            e.target.classList.add('active')
          })
        })

        // Automatically load documents for the first category on page load
        renderDocumentsForCategory(categories[0].category_id)
        const firstCategoryLink = document.querySelector('.category-link')
        if (firstCategoryLink) {
          firstCategoryLink.classList.add('active')
        }
      } else {
        categoryListEl.innerHTML =
          '<h3>Categories</h3><p>No categories found.</p>'
        documentListEl.innerHTML =
          '<h3>Documents</h3><p>No documents to display.</p>'
      }
    } catch (error) {
      console.error('Error fetching categories:', error)
      categoryListEl.innerHTML = `<p style="color: #ef4444;">Failed to load data: ${error.message}</p>`
    }
  }

  window.onload = fetchCategories
}
