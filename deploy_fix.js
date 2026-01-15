const FtpDeploy = require("ftp-deploy");
const ftpDeploy = new FtpDeploy();

const config = {
    user: "u315410518.ziipvet",
    // Decoded password from possible HTML entity artifact
    password: ">L?0soiQPP!DWu3+",
    host: "147.79.84.227",
    port: 21,
    localRoot: __dirname + "/",
    remoteRoot: "/public_html/app/",
    include: ["*.php", "*.css", "*.js", "config/*", "css/*", "js/*", "img/*", "vendas/*", "financeiro/*", "uploads/*"],
    exclude: [
        "node_modules/**",
        ".git/**",
        ".vscode/**",
        "backup.sql",
        "setup_database.php",
        "import_chunked.php",
        "check_status.php",
        "check_users.php",
        "debug_import_usuarios.php",
        "force_import.php",
        "deploy.js",
        "package.json",
        "package-lock.json",
        "**/*.md"
    ],
    deleteRemote: false,
    forcePasv: true,
    sftp: false
};

console.log("Tentando conectar com senha decodificada...");

ftpDeploy
    .deploy(config)
    .then(res => console.log("Upload concluído com sucesso!"))
    .catch(err => console.log("Erro no deploy:", err));
