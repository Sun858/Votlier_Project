CREATE DATABASE voting_system;

USE voting_system;

CREATE TABLE users (
	user_id INT AUTO_INCREMENT PRIMARY KEY, --Unique User Identifier
    first_name VARBINARY(50) NOT NULL, --Columns with VARBINARY inside is used to store encrypted AES binary
    middle_name VARBINARY(50) NOT NULL,
    last_name VARBINARY(50) NOT NULL,
    email VARBINARY(255) NOT NULL UNIQUE,
    hash_password VARBINARY(255) NOT NULL,
    salt VARBINARY(255) NOT NULL,
    iterations INT NOT NULL, --Number of Iterations for PBKDF2
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
CREATE TABLE administration (
	admin_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARBINARY(50) NOT NULL, --Columns with VARBINARY inside is used to store encrypted AES binary
    middle_name VARBINARY(50) NOT NULL,
    last_name VARBINARY(50) NOT NULL,
    email VARBINARY(255) NOT NULL UNIQUE,
    hash_password VARBINARY(255) NOT NULL,
    salt VARBINARY(255) NOT NULL,
    iterations INT NOT NULL, --Number of Iterations for PBKDF2
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE election (
	poll_id INT AUTO_INCREMENT PRIMARY KEY,
    election_type VARCHAR(50) NOT NULL,
    election_name VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME
    );
    
CREATE TABLE candidates (
	candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    party VARCHAR(255),
    candidate_symbol VARCHAR(255),
    admin_id INT NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES election(poll_id),
    FOREIGN KEY (admin_id) REFERENCES administration(admin_id)
    );
    
CREATE TABLE ballot (
	ballot_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    poll_id INT NOT NULL,
    candidate_id INT NOT NULL,
    preference_rank INT NOT NULL,
    dateandtime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encrypted_ballot TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (poll_id) REFERENCES election(poll_id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id),
    UNIQUE (user_id, poll_id, preference_rank)
    );

CREATE TABLE tally (
	poll_id INT NOT NULL,
    candidate_id INT NOT NULL,
    total_votes INT DEFAULT 0,
    r1_votes INT DEFAULT 0,
    r2_votes INT DEFAULT 0,
    r3_votes INT DEFAULT 0, /*Doing this we can ensure that each candidate gets a certain amount of 
    ranked votes so we can aggregate into a total vote number for the winner. 
    Still doing some brainstorming for this now that we are working on the project though. But the idea is there :() P 
    */
    updatetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poll_id) REFERENCES election(poll_id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(candidate_id), 
    PRIMARY KEY (poll_id, candidate_id)
);
    
    
/* Thoughts from Deniz - Instead of creating a whole new table to provide the results, we can use
SELECT column.candidate_name and whatever, FROM tally JOIN both columns where the poll_ID is equal to
the poll_ID in which the admin selected compile results in the site and order by total_votes. 
Then we can possibly display the results with just a query and displaying a table on the screen.
This is able to be done cause the votes are already compiled within the tally table automatically. 
*/
    

