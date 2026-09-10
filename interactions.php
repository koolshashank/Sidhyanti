<?php
$pageTitle = 'Interactions';
$crumb = 'Calls, field visits & center meetings log';
$active = 'interactions';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO Interaction (MemberID, InteractionTypeID, InteractionDate, StaffID, OutcomeID, NextFollowupDate, Remarks) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['member_id'], $_POST['type_id'], $_POST['interaction_date'], current_staff()['StaffID'],
                     $_POST['outcome_id'] ?: null, $_POST['next_followup'] ?: null, $_POST['remarks']]);
    header('Location: interactions.php'); exit;
}

$members = $pdo->query("SELECT TOP 300 MemberID, FirstName, LastName FROM Member ORDER BY FirstName")->fetchAll();
$types = $pdo->query("SELECT TypeID, TypeName FROM Interaction_Type_Master ORDER BY TypeName")->fetchAll();
$outcomes = $pdo->query("SELECT OutcomeID, OutcomeName FROM Interaction_Outcome_Master ORDER BY OutcomeName")->fetchAll();

$typeFilter = $_GET['type'] ?? '';
$where = $typeFilter ? "WHERE it.TypeName = ?" : "";
$stmt = $pdo->prepare("
  SELECT TOP 100 i.*, m.FirstName, m.LastName, it.TypeName, s.FullName AS staff_name
  FROM Interaction i
  LEFT JOIN Member m ON i.MemberID = m.MemberID
  LEFT JOIN Interaction_Type_Master it ON i.InteractionTypeID = it.TypeID
  LEFT JOIN Staff s ON i.StaffID = s.StaffID
  $where
  ORDER BY i.InteractionDate DESC
");
$stmt->execute($typeFilter ? [$typeFilter] : []);
$interactions = $stmt->fetchAll();
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="filters">
      <?php foreach ($types as $t): ?>
        <a class="btn-outline-x" style="<?= $typeFilter==$t['TypeName']?'background:var(--navy);color:#fff;':'' ?>" href="?type=<?= urlencode($t['TypeName']) ?>"><?= htmlspecialchars($t['TypeName']) ?></a>
      <?php endforeach; ?>
      <a class="btn-outline-x" style="<?= !$typeFilter?'background:var(--navy);color:#fff;':'' ?>" href="?type=">All</a>
    </div>
    <div class="card-x">
      <table class="table-x">
        <tr><th>Date</th><th>Member</th><th>Type</th><th>Staff</th><th>Next Follow-up</th></tr>
        <?php foreach ($interactions as $i): ?>
        <tr>
          <td><?= fdate($i['InteractionDate']) ?></td>
          <td><a href="member_view.php?id=<?= $i['MemberID'] ?>"><?= htmlspecialchars($i['FirstName'].' '.$i['LastName']) ?></a></td>
          <td><?= htmlspecialchars($i['TypeName']) ?></td>
          <td><?= htmlspecialchars($i['staff_name'] ?? 'Unknown') ?></td>
          <td><?= fdate($i['NextFollowupDate']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$interactions): ?><tr><td colspan="5"><div class="empty-state">No interactions logged.</div></td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-x">
      <div class="section-title">Log New Interaction</div>
      <form method="POST">
        <label class="form-label">Member</label>
        <select class="form-select mb-2" name="member_id" required>
          <?php foreach ($members as $m): ?><option value="<?= $m['MemberID'] ?>"><?= htmlspecialchars($m['FirstName'].' '.$m['LastName']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Type</label>
        <select class="form-select mb-2" name="type_id">
          <?php foreach ($types as $t): ?><option value="<?= $t['TypeID'] ?>"><?= htmlspecialchars($t['TypeName']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Date</label>
        <input type="date" class="form-control mb-2" name="interaction_date" value="<?= date('Y-m-d') ?>" required>
        <label class="form-label">Outcome</label>
        <select class="form-select mb-2" name="outcome_id">
          <?php foreach ($outcomes as $o): ?><option value="<?= $o['OutcomeID'] ?>"><?= htmlspecialchars($o['OutcomeName']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Next Follow-up</label>
        <input type="date" class="form-control mb-2" name="next_followup">
        <label class="form-label">Remarks</label>
        <textarea class="form-control mb-3" name="remarks" rows="3"></textarea>
        <button class="btn-navy w-100" type="submit">Save Interaction</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
