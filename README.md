# Votlier
## A Secure Online Voting System

This is a Online Voting System built by Deniz Ismail, Sanjay Sapkota and Sharmarke Farah for Bachelor of Cyber Security (Third Year)

## Project Structure
```
📁Project-root
    📁Assets            # All our static assets live here
        📁css           # Stylesheets go here
        📁images        # Image files for the project
        📁js            # JavaScript files
    📁init              # Initialisation files (Contains Schema for build)
    📁DatabaseConnection # Database connection scripts and configs
    📁Docker            # Contains base docker file for the project
    📁includes          # Reusable PHP components and functions
    📁controllers          # Contains the controllers of the various pages
    📁pages             # Individual page files (PHP or HTML)
    📄index.html        # Main entry point of our website
    📄README.md         # Project documentation
    📄docker-compose.yml # This is the compose file for docker (What docker bases the build upon)
    📄.env              # Contains the static information for database logon (Must be inside the folder, gain elsewhere)
```
Key points to remember:
1. Keep all static assets (CSS, JS, images) in the 'Assets' folder.
2. Use 'DatabaseConnection' for anything related to our database setup.
3. Put reusable code snippets or functions in the 'includes' directory.
4. Individual pages of our site go in the 'pages' folder.
5. Always keep the README.md updated with important project info.

## Project Initialisation
To easily run this website, it is recommended to use Docker. Install at https://docs.docker.com/desktop/
To create the docker instance for this website and database run the following prompts into your preferred terminal:
```
cd .\*navigate\to\project-root\directory
```
This is needed to first navigate to the main directory of the files.
Then all you need to do is build from the docker-compose.yml file (Ensure you are in the directory with this file). 
```
docker-compose up --build
```
If you need to make changes to the docker files or for whatever reason need to rebuild the docker due to any changes, use this:
- Keep in mind that this deletes the containers, networks and images created by the docker-compose.yml file.
```
docker-compose down 
```
- If you wanted to also remove the database data you can use either of these commands.
```
docker-compose down -v
```
Or find the data volume with this:
```
docker volume ls
```
And delete it with:
```
docker volume rm [volume_name]
``` 
- Dont actually input the [ ] tag, only the plaintext.

## Administrator Login:
Email: bigadmin@live.com.au
Password: administrator

