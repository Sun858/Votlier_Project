-- This script creates a simple two-table schema for a documentation system.
-- The 'documents' table stores the content, and the 'categories' table
-- provides a way to organize the documents.

-- Create the 'categories' table first, as it's referenced by the 'documents' table.
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the 'documents' table.
CREATE TABLE documents (
    document_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Define the foreign key constraint to link documents to categories.
    -- If a category is deleted, all documents in that category will also be deleted.
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

-- Optional: Create some initial categories to get started.
INSERT INTO categories (category_name) VALUES ('Getting Started');
INSERT INTO categories (category_name) VALUES ('API Reference');
INSERT INTO categories (category_name) VALUES ('User Guides');
INSERT INTO categories (category_name) VALUES ('Troubleshooting');
