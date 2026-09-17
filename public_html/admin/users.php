<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
requireAdminAuth();

$pageTitle  = 'Users';
$activePage = 'users';

// Refresh admin status from DB so stale sessions never hide the Add button
try {
    $meStmt = $pdo->prepare("SELECT role, is_administrator FROM admin_users WHERE id = ?");
    $meStmt->execute([$_SESSION['admin_id']]);
    $meRow = $meStmt->fetch();
    if ($meRow) {
        $_SESSION['admin_is_administrator'] = (bool)($meRow['is_administrator']
            ?? ($meRow['role'] === 'super_admin'));
        $_SESSION['admin_role'] = $meRow['role'] ?? 'editor';
    }
} catch (PDOException $e) {
    // is_administrator column not yet added — fall back to role
    $_SESSION['admin_is_administrator'] = ($_SESSION['admin_role'] ?? '') === 'super_admin';
}

$action = $_GET['action'] ?? 'list';
$userId = (int)($_GET['id'] ?? 0);

$msg   = '';
$error = '';

$FEATURES    = getPermissionFeatures();
$DEPARTMENTS = ['', 'Finance', 'Operations', 'Marketing', 'HR', 'IT', 'Management', 'Other'];

// ── Handle DELETE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!isSuper()) {
        $error = 'Only Administrators can delete users.';
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $did = (int)$_POST['delete_id'];
        if ($did === (int)$_SESSION['admin_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $superCnt = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_administrator=1")->fetchColumn();
            $t = $pdo->prepare("SELECT is_administrator FROM admin_users WHERE id=?");
            $t->execute([$did]);
            $tr = $t->fetch();
            if ($tr && $tr['is_administrator'] && $superCnt <= 1) {
                $error = 'Cannot delete the last Administrator account.';
            } else {
                $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$did]);
                header('Location: users.php?msg=deleted');
                exit;
            }
        }
    }
}

// ── Handle SAVE (add / edit) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $editId       = (int)($_POST['edit_id'] ?? 0);
        $username     = trim($_POST['username'] ?? '');
        $firstName    = trim($_POST['first_name'] ?? '');
        $lastName     = trim($_POST['last_name'] ?? '');
        $emailAddr    = trim($_POST['email'] ?? '');
        $department   = trim($_POST['department'] ?? '');
        $isAdmin      = isset($_POST['is_administrator']) ? 1 : 0;
        $isStaff      = isset($_POST['is_staff']) ? 1 : 0;
        $password     = $_POST['password'] ?? '';
        $confirm      = $_POST['confirm_password'] ?? '';
        $sendWelcome  = isset($_POST['send_welcome']);

        // Editors can only edit themselves
        if (!isSuper() && $editId !== (int)$_SESSION['admin_id']) {
            $error = 'You can only edit your own account.';
        } elseif (!isSuper() && $editId === 0) {
            $error = 'Only Administrators can create new users.';
        } elseif (empty($username) || empty($firstName) || empty($lastName) || empty($emailAddr)) {
            $error = 'First name, last name, email and username are required.';
        } elseif (!filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($editId === 0 && empty($password)) {
            $error = 'Password is required for new users.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== '' && $password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Username uniqueness
            $dup = $pdo->prepare("SELECT id FROM admin_users WHERE username=? AND id!=?");
            $dup->execute([$username, $editId]);
            if ($dup->fetch()) {
                $error = "Username \"$username\" is already taken.";
            } else {
                // Build permissions JSON (only relevant for non-admins)
                $rawPerms = $_POST['perms'] ?? [];
                $permsArr = [];
                foreach ($FEATURES as $feat => $caps) {
                    $key = strtolower($feat);
                    $permsArr[$key] = array_values(array_filter(
                        array_keys($caps),
                        fn($c) => in_array($c, (array)($rawPerms[$key] ?? []))
                    ));
                }
                $permissionsJson = json_encode($permsArr);

                // Role derived from is_administrator
                $role = $isAdmin ? 'super_admin' : 'editor';

                // Profile image
                $imagePath = trim($_POST['existing_image'] ?? '');
                if (!empty($_FILES['profile_image']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $_FILES['profile_image']['size'] <= 3*1024*1024) {
                        $dir = '../assets/images/users/';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname = 'user_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . $fname)) {
                            $imagePath = 'assets/images/users/' . $fname;
                        }
                    }
                }

                if ($editId > 0) {
                    // Prevent self-demotion for the only admin
                    if ($editId === (int)$_SESSION['admin_id'] && !$isAdmin) {
                        $superCnt = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_administrator=1")->fetchColumn();
                        if ($superCnt <= 1) { $error = 'Cannot remove Administrator from the only admin account.'; }
                    }
                    if (!$error) {
                        $sql = "UPDATE admin_users SET
                            username=?, first_name=?, last_name=?, email=?,
                            department=?, is_administrator=?, is_staff=?,
                            role=?, permissions=?, profile_image=?";
                        $params = [$username,$firstName,$lastName,$emailAddr,
                                   $department,$isAdmin,$isStaff,
                                   $role,$permissionsJson,$imagePath];
                        if ($password !== '') {
                            $sql .= ", password=?";
                            $params[] = password_hash($password, PASSWORD_BCRYPT);
                        }
                        $sql .= " WHERE id=?";
                        $params[] = $editId;
                        $pdo->prepare($sql)->execute($params);
                        header('Location: users.php?msg=updated');
                        exit;
                    }
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $pdo->prepare(
                        "INSERT INTO admin_users
                         (username,password,first_name,last_name,email,department,
                          is_administrator,is_staff,role,permissions,profile_image)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                    )->execute([$username,$hash,$firstName,$lastName,$emailAddr,$department,
                                $isAdmin,$isStaff,$role,$permissionsJson,$imagePath]);

                    // Welcome email
                    if ($sendWelcome) {
                        $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                                  . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/login.php';
                        $body = "Hello $firstName,<br><br>"
                              . "Your admin account for <strong>" . COMPANY_NAME . "</strong> has been created.<br><br>"
                              . "<strong>Username:</strong> $username<br>"
                              . "<strong>Password:</strong> $password<br>"
                              . "<strong>Login URL:</strong> <a href='$loginUrl'>$loginUrl</a><br><br>"
                              . "Please change your password after your first login.<br><br>"
                              . "— " . COMPANY_NAME . " Team";
                        sendEmail($emailAddr, 'Your Admin Account – ' . COMPANY_NAME, $body);
                    }
                    header('Location: users.php?msg=added');
                    exit;
                }
            }
        }
    }
    // Stay on form if error
    $action = ($userId > 0) ? 'edit' : 'add';
}

// Flash messages
if (isset($_GET['msg'])) {
    $msgs = ['added'=>'User created successfully.','updated'=>'User updated.','deleted'=>'User deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// ── Load user for editing ──────────────────────────────────
$editUser = null;
if (in_array($action, ['edit']) && $userId > 0) {
    if (!isSuper() && $userId !== (int)$_SESSION['admin_id']) {
        header('Location: users.php'); exit;
    }
    $s = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
    $s->execute([$userId]);
    $editUser = $s->fetch() ?: null;
    if ($editUser) {
        $editUser['permissions_arr'] = json_decode($editUser['permissions'] ?? '{}', true) ?? [];
    }
}

// ── List ───────────────────────────────────────────────────
try {
    $allUsers = $pdo->query("SELECT * FROM admin_users ORDER BY is_administrator DESC, username")->fetchAll();
} catch (PDOException $e) {
    $allUsers = $pdo->query("SELECT * FROM admin_users ORDER BY username")->fetchAll();
    $error = 'Please run <a href="../add_user_columns.php"><strong>add_user_columns.php</strong></a> to update the database.';
}

$csrfToken = generateCsrfToken();

include 'header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($msg)?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?=$error?></div>
<?php endif; ?>

<?php if (!isSuper()): ?>
<div class="alert alert-warning" style="margin-bottom:20px;">
    <i class="fas fa-info-circle"></i>
    You are logged in as <strong>Editor</strong>. You can only edit your own account.
</div>
<?php endif; ?>

<?php /* ======================================================
         LIST VIEW
     ====================================================== */ ?>
<?php if ($action === 'list'): ?>

<div class="page-sub-header">
    <div>
        <h2 style="font-size:18px;">Staff Members</h2>
        <p style="color:#94a3b8;font-size:13px;"><?=count($allUsers)?> account<?=count($allUsers)!==1?'s':''?></p>
    </div>
    <?php if (isSuper()): ?>
    <a href="users.php?action=add" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add New Staff Member
    </a>
    <?php endif; ?>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allUsers as $u): ?>
            <?php
                $initials = strtoupper(substr($u['first_name']??'',0,1) . substr($u['last_name']??'',0,1));
                if (!$initials) $initials = strtoupper(substr($u['username'],0,2));
                $canEdit = isSuper() || (int)$u['id'] === (int)$_SESSION['admin_id'];
                $canDel  = isSuper() && (int)$u['id'] !== (int)$_SESSION['admin_id'];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if (!empty($u['profile_image'])): ?>
                            <img src="../<?=htmlspecialchars($u['profile_image'])?>" class="user-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder"><?=$initials?></div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:600;">
                                <?=htmlspecialchars(trim(($u['first_name']??'').' '.($u['last_name']??''))) ?: htmlspecialchars($u['username'])?>
                                <?php if ((int)$u['id']===(int)$_SESSION['admin_id']): ?>
                                    <span class="badge badge-orange" style="margin-left:4px;">You</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;">@<?=htmlspecialchars($u['username'])?></div>
                        </div>
                    </div>
                </td>
                <td style="color:#475569;"><?=htmlspecialchars($u['email']??'—')?></td>
                <td><?=htmlspecialchars($u['department']??'—')?></td>
                <td>
                    <?php if (!empty($u['is_administrator'])): ?>
                        <span class="badge badge-green"><i class="fas fa-shield-alt"></i> Administrator</span>
                    <?php else: ?>
                        <span class="badge badge-gray"><i class="fas fa-pencil-alt"></i> Staff Member</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($u['is_staff'])): ?>
                        <span class="badge badge-green">Active</span>
                    <?php else: ?>
                        <span class="badge badge-gray">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($canEdit): ?>
                    <a href="users.php?action=edit&id=<?=$u['id']?>"
                       class="btn btn-outline btn-sm" style="gap:5px;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php endif; ?>
                    <?php if ($canDel): ?>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Delete <?=htmlspecialchars(addslashes($u['username']))?> permanently?');">
                        <?=csrfField()?>
                        <input type="hidden" name="delete_id" value="<?=$u['id']?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php /* ======================================================
         ADD / EDIT FORM
     ====================================================== */ ?>
<?php else: ?>

<div class="page-sub-header">
    <div>
        <h2><?=$editUser ? 'Edit Staff Member' : 'Add New Staff Member'?></h2>
        <p style="color:#94a3b8;font-size:13px;">
            <?=$editUser ? htmlspecialchars(trim(($editUser['first_name']??'').' '.($editUser['last_name']??''))) ?: htmlspecialchars($editUser['username']) : 'Fill in the details below'?>
        </p>
    </div>
    <a href="users.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Users</a>
</div>

<form method="POST" action="users.php" enctype="multipart/form-data">
    <?=csrfField()?>
    <input type="hidden" name="save_user" value="1">
    <input type="hidden" name="edit_id" value="<?=$editUser?$editUser['id']:0?>">
    <input type="hidden" name="existing_image" value="<?=htmlspecialchars($editUser['profile_image']??'')?>">

    <div class="card">
        <!-- Tabs -->
        <div class="tabs-bar">
            <button type="button" class="tab-btn active" data-tab="profile">
                <i class="fas fa-user"></i> Profile
            </button>
            <button type="button" class="tab-btn" data-tab="permissions">
                <i class="fas fa-lock"></i> Permissions
            </button>
        </div>

        <!-- ── PROFILE TAB ── -->
        <div id="tab-profile" class="tab-pane active">

            <!-- Administrator / Staff checkboxes -->
            <?php if (isSuper()): ?>
            <div style="margin-bottom:20px;">
                <label class="check-group">
                    <input type="checkbox" name="is_administrator" value="1" id="isAdminChk"
                           <?=!empty($editUser['is_administrator'])?'checked':''?>>
                    <div>
                        <div class="check-group-label"><i class="fas fa-shield-alt" style="color:var(--primary);margin-right:5px;"></i>Administrator</div>
                        <div class="check-group-sub">Full access to all features and settings</div>
                    </div>
                </label>
                <label class="check-group">
                    <input type="checkbox" name="is_staff" value="1"
                           <?=!isset($editUser)||!empty($editUser['is_staff'])?'checked':''?>>
                    <div>
                        <div class="check-group-label"><i class="fas fa-id-badge" style="color:#94a3b8;margin-right:5px;"></i>Active Staff Member</div>
                        <div class="check-group-sub">Uncheck to deactivate this account</div>
                    </div>
                </label>
            </div>
            <?php endif; ?>

            <!-- Profile image -->
            <div class="form-group">
                <label>Profile Image</label>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px;">
                    <?php if (!empty($editUser['profile_image'])): ?>
                        <img src="../<?=htmlspecialchars($editUser['profile_image'])?>" class="user-avatar-lg">
                    <?php else: ?>
                        <?php
                        $ini = strtoupper(substr($editUser['first_name']??'',0,1).substr($editUser['last_name']??'',0,1));
                        if (!$ini) $ini = strtoupper(substr($editUser['username']??'U',0,2));
                        ?>
                        <div style="width:80px;height:80px;border-radius:50%;background:var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--gray-600);"><?=$ini?></div>
                    <?php endif; ?>
                    <div>
                        <input type="file" name="profile_image" accept="image/*" class="form-control"
                               style="max-width:300px;" onchange="previewAvatar(this)">
                        <div class="form-hint">JPG, PNG, WEBP – max 3 MB</div>
                    </div>
                </div>
            </div>

            <!-- Name + Email -->
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?=htmlspecialchars($editUser['first_name']??$_POST['first_name']??'')?>">
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?=htmlspecialchars($editUser['last_name']??$_POST['last_name']??'')?>">
                </div>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" class="form-control" required
                       value="<?=htmlspecialchars($editUser['email']??$_POST['email']??'')?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required autocomplete="off"
                           value="<?=htmlspecialchars($editUser['username']??$_POST['username']??'')?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" class="form-control">
                        <?php foreach ($DEPARTMENTS as $d): ?>
                        <option value="<?=htmlspecialchars($d)?>"
                            <?=(($editUser['department']??'') === $d)?'selected':''?>>
                            <?=$d ?: 'Select department…'?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Password -->
            <hr class="divider">
            <div style="font-size:13.5px;font-weight:700;color:var(--gray-800);margin-bottom:14px;">
                <i class="fas fa-key" style="color:var(--primary);margin-right:6px;"></i>
                <?=$editUser ? 'Password <span style="font-weight:400;color:#94a3b8;font-size:12.5px;">(leave blank to keep current)</span>' : 'Password'?>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label><?=$editUser ? 'New Password' : 'Password *'?></label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="pwdField" class="form-control"
                               <?=$editUser?'':'required'?> autocomplete="new-password" minlength="8"
                               style="padding-right:42px;"
                               placeholder="<?=$editUser?'Leave blank to keep current':'Min. 8 characters'?>">
                        <button type="button" onclick="togglePwd('pwdField',this)"
                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:15px;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           autocomplete="new-password" placeholder="Repeat password">
                </div>
            </div>

            <?php if (!$editUser && isSuper()): ?>
            <label class="check-group" style="border-color:rgba(0,177,64,.3);background:rgba(0,177,64,.03);">
                <input type="checkbox" name="send_welcome" value="1" checked>
                <div>
                    <div class="check-group-label"><i class="fas fa-envelope" style="color:var(--primary);margin-right:5px;"></i>Send welcome email</div>
                    <div class="check-group-sub">Sends login credentials to the user's email address</div>
                </div>
            </label>
            <?php endif; ?>
        </div><!-- end profile tab -->

        <!-- ── PERMISSIONS TAB ── -->
        <div id="tab-permissions" class="tab-pane">
            <?php
            $userPerms = $editUser['permissions_arr'] ?? [];
            $userIsAdmin = !empty($editUser['is_administrator']);
            ?>

            <?php if ($userIsAdmin): ?>
            <div class="admin-perm-note">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <strong>Administrator</strong> — This user has full access to all features.
                    Permissions cannot be restricted for Administrators.
                </div>
            </div>
            <?php else: ?>
            <p style="font-size:13px;color:#94a3b8;margin-bottom:20px;">
                Check the capabilities this staff member is allowed to perform.
                Unchecked items will be hidden or blocked.
            </p>

            <div class="table-card" style="box-shadow:none;border:1.5px solid var(--gray-200);">
                <table class="perm-table">
                    <thead>
                        <tr>
                            <th style="width:200px;">Feature</th>
                            <th>Capabilities</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($FEATURES as $feat => $caps): ?>
                    <?php $featKey = strtolower($feat); ?>
                    <tr>
                        <td class="perm-feature-name"><?=ucfirst($feat)?></td>
                        <td class="perm-caps">
                            <?php foreach ($caps as $capKey => $capLabel): ?>
                            <?php $checked = in_array($capKey, (array)($userPerms[$featKey]??[]), true); ?>
                            <label class="perm-cap-item">
                                <input type="checkbox"
                                       name="perms[<?=$featKey?>][]"
                                       value="<?=$capKey?>"
                                       <?=$checked?'checked':''?>>
                                <?=htmlspecialchars($capLabel)?>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div><!-- end permissions tab -->

    </div><!-- end card -->

    <!-- Action bar -->
    <div style="display:flex;gap:12px;margin-top:20px;align-items:center;">
        <button type="submit" class="btn btn-primary" style="padding:11px 28px;">
            <i class="fas fa-save"></i>
            <?=$editUser ? 'Save Changes' : 'Create Staff Member'?>
        </button>
        <a href="users.php" class="btn btn-outline">Cancel</a>
    </div>
</form>

<?php endif; /* end add/edit view */ ?>

<script>
// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Toggle admin checkbox → update permissions tab note
const adminChk = document.getElementById('isAdminChk');
if (adminChk) {
    adminChk.addEventListener('change', function() {
        const note  = document.querySelector('.admin-perm-note');
        const table = document.querySelector('.perm-table')?.closest('.table-card');
        const hint  = document.querySelector('#tab-permissions > p');
        if (this.checked) {
            if (note)  note.style.display  = 'flex';
            if (table) table.style.display = 'none';
            if (hint)  hint.style.display  = 'none';
        } else {
            if (note)  note.style.display  = 'none';
            if (table) table.style.display = 'block';
            if (hint)  hint.style.display  = 'block';
        }
    });
}

// Show/hide password
function togglePwd(id, btn) {
    const el = document.getElementById(id);
    const ic = btn.querySelector('i');
    el.type = el.type === 'password' ? 'text' : 'password';
    ic.classList.toggle('fa-eye');
    ic.classList.toggle('fa-eye-slash');
}

// Profile image preview
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        let img = input.closest('.form-group').querySelector('img.user-avatar-lg');
        let ph  = input.closest('.form-group').querySelector('div[style*="border-radius:50%"]');
        if (!img) {
            img = document.createElement('img');
            img.className = 'user-avatar-lg';
            input.closest('div').insertBefore(img, input.closest('div').firstChild);
            if (ph) ph.style.display = 'none';
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<?php include 'footer.php'; ?>
