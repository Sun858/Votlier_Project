CREATE DATABASE IF NOT EXISTS voting_system;

USE voting_system;

-- Table Structure --

-- Users Table --
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name BLOB NOT NULL,
    middle_name BLOB NOT NULL,
    last_name BLOB NOT NULL,
    email BLOB NOT NULL,
    email_blind_index BINARY(32) NOT NULL, -- Blind Index for secure lookup
    hash_password VARBINARY(32) NOT NULL,
    salt VARBINARY(16) NOT NULL,
    iterations INT NOT NULL,
    iv VARBINARY(16) NOT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email_blind_index (email_blind_index)
);

-- Administration Table --
CREATE TABLE IF NOT EXISTS administration (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name BLOB NOT NULL,
    middle_name BLOB NOT NULL,
    last_name BLOB NOT NULL,
    email BLOB NOT NULL,
    email_blind_index BINARY(32) NOT NULL, -- Blind Index for secure lookup
    hash_password VARBINARY(32) NOT NULL,
    salt VARBINARY(16) NOT NULL,
    iterations INT NOT NULL,
    iv VARBINARY(16) NOT NULL,
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_email_blind_index (email_blind_index)
);



-- Election Table --
CREATE TABLE IF NOT EXISTS `election` (
    poll_id INT AUTO_INCREMENT PRIMARY KEY,
    election_type VARCHAR(50) NOT NULL,
    election_name VARCHAR(255) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME DEFAULT NULL
);

-- Candidates Table --
CREATE TABLE IF NOT EXISTS `candidates` (
    candidate_id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    party VARCHAR(255),
    candidate_symbol VARCHAR(255),
    admin_id INT NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES election(poll_id),
    FOREIGN KEY (admin_id) REFERENCES administration(admin_id)
);

-- Ballot Table --
CREATE TABLE IF NOT EXISTS `ballot` (
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
    UNIQUE (user_id, poll_id, preference_rank),
    CHECK (preference_rank BETWEEN 1 AND 5)  -- Ensure preference rank is between 1 and 5
);

-- Tally Table --
CREATE TABLE IF NOT EXISTS `tally` (
    poll_id INT NOT NULL,
    candidate_id INT NOT NULL,
    total_votes INT DEFAULT 0,
    r1_votes INT DEFAULT 0,
    r2_votes INT DEFAULT 0,
    r3_votes INT DEFAULT 0,
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
    

-- Dummy administrator user to be inserted into the database is below, because our project doesn't allow the creation of an Administrator in the user interface for security reasons.
INSERT INTO `administration` (
  `admin_id`,
  `first_name`,
  `middle_name`,
  `last_name`,
  `email`,
  `email_blind_index`,
  `hash_password`,
  `salt`,
  `iterations`,
  `iv`,
  `date_created`
) VALUES (
  1,
  0xd9045473871485880d2b1ba04a28822c,
  0x83d06e757ff57e640127770e7b20b9ca,
  0x8544aa4941152796a54b57e9fdaada1a,
  0x2c49a4f966291c2619cf27f7e9b4a3ac5cac7c2bd7ee0b2110619feecd200636,
  0x7e13ad2689d6467d9322717e70e5f4f013790380165eb14f9526ddf7513fca5d,
  0xcbe495402bcf497e3df8096d34ca7158bf460ecd440dad98940c0dc15d707af6,
  0xae0545a33a9e2fe7edeafcad391074fe,
  100000,
  0x667e14898b4a35b0698d272fead0cace,
  '2025-06-17 12:17:17'
);