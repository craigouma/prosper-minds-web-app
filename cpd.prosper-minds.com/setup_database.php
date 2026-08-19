<?php
require_once 'config.php';

$conn = getDBConnection();

// Create events table
$events_sql = "CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `dates` varchar(100) NOT NULL,
  `venue` varchar(100) NOT NULL,
  `target_audience` varchar(255) NOT NULL,
  `tagline` varchar(255) NOT NULL,
  `objective` text NOT NULL,
  `method` text NOT NULL,
  `benefit` text NOT NULL,
  `cost` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($events_sql) === FALSE) {
    die("Error creating events table: " . $conn->error);
}

// Create registrations table
$registrations_sql = "CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `organization` varchar(255) NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($registrations_sql) === FALSE) {
    die("Error creating registrations table: " . $conn->error);
}

// Insert sample events data
$events_data = [
    ['Foundations of Foresight', 'Step into a room where your mind wakes up. In January, you learn how to sense financial trouble before it even forms. Through fast, hands-on sprints, your thinking sharpens until you become the person people look at and whisper, \"How did you see that coming?\"', 'Monday 26 – Friday 30 January 2026', 'Nairobi, Kenya', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Build the bedrock for strategic public finance.', 'Master PFM, IPSAS & IFRS in real-world African contexts.', 'Hands-on sprints; peer-to-peer coaching; zero lectures.', 'Be the one who spots problems before anyone else does.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Data Awakening', 'This is the month where numbers start behaving like clues. You discover how data hides danger, reveals opportunity, and shows you what others can’t see. By the end, every spreadsheet feels like a map—and you’re the one who knows how to read it.', 'Monday 23 – Friday 27 February 2026', 'Dubai, UAE', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Turn numbers into foresight.', 'Extract insights, detect risks, make data-driven decisions.', 'Live analytics labs with real government data.', 'Turn boring numbers into decisions people rely on.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Strategic Compliance', 'Here, rules stop feeling heavy. They start feeling powerful. You learn how to turn IPSAS and IFRS into strategic tools that make you stand out. When others follow the rules, you’ll use them to shape smarter decisions and bigger wins.', 'Monday 23 – Friday 27 March 2026', 'Singapore', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Compliance is the floor, strategy is the ceiling.', 'Transform IPSAS & IFRS Application into strategic action.', 'Scenario simulations; real-world reporting challenges.', 'Make rules work for you—and stand out as a strategic thinker.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Automation Ascendancy', 'April is where your workload finally bows to you. AI and automation take over the boring tasks, leaving you free to think big and lead boldly. You stop being buried in reports—and start being the one who changes how things get done.', 'Monday 27 April – Friday 1 May 2026', 'Nairobi, Kenya', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Free teams to think, not compile.', 'Automate PFM reporting; save time, reduce errors.', 'AI-driven labs; hands-on automation exercises.', 'Free yourself from busywork and shine on high-impact tasks.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Decision Dynamics', 'Imagine stepping into a crisis room and feeling calm. In May, you train your mind to think fast, clear, and strong. With cabinet-style drills and wild simulations, you become the person others turn to when everything is on fire—because you make the decisions that save the day.', 'Monday 25 – Friday 29 May 2026', 'Dubai, UAE', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Every financial choice counts.', 'Strengthen PFM decision-making under extreme constraints.', 'Cabinet-style simulations; live crises; team problem-solving; Moonshot Thinking.', 'Make decisions others wish they could make—and make them fast.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Audit Reimagined', 'Auditing becomes detective work. You sharpen your instincts, spot the strange patterns, and catch the things many miss. In June, you gain the kind of insight that makes leaders trust your judgment instantly.', 'Monday 22 – Friday 26 June 2026', 'Singapore', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'From checklists to insight.', 'Turn audits into predictive governance tools: Effective Audit Committees & Internal Auditors', 'Live audit simulations; anomaly detection exercises.', 'Find what others miss and earn trust instantly.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Transparency Engine', 'Here, you learn how to turn complex financial stories into simple truths people believe. Dashboards, visuals, and real-world labs teach you how to build trust you can feel in the room. By the end, your work won’t just inform—it will inspire confidence.', 'Monday 27 – Friday 31 July 2026', 'Nairobi, Kenya', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Build public trust through insight.', 'Link reporting to citizen confidence: Stakeholder facing Data Analytics & Automation.', 'Dashboards, communications, real-world transparency labs.', 'Build confidence and respect that others can’t ignore.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Forecast Frontier', 'This month teaches you how to see the future in the numbers. You build models, stress-test impossible scenarios, and read patterns like a weather forecaster of government finance. You become the one everyone asks: “What’s coming next?”', 'Monday 24 – Friday 28 August 2026', 'Dubai, UAE', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Predict to perform.', 'Build predictive data models and scenario plans.', 'Multi-year forecasts; black-swan stress tests; Montecarlo Analysis.', 'See what’s coming before anyone else—be the go-to expert.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Strategic Investment Lab', 'Budgets turn into engines of progress. In September, you learn how to use sustainability, value, and long-term thinking to make money work harder. You walk out knowing how to turn every investment into a story of growth.', 'Monday 28 September – Friday 2 October 2026', 'Singapore', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Turn budgets into growth engines.', 'Use finance and sustainability to drive impact- IPSAS Sustainability Reporting Standards.', 'Investment simulations; ESG-based decision sprints.', 'Turn budgets into results people admire—and remember.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Policy Alchemy', 'This month turns you into a policy transformer. You take raw data, complex rules, and real pressures—and shape them into policies that actually work on the ground. Your ideas stop being ideas. They become impact', 'Monday 26 – Friday 30 October 2026', 'Nairobi, Kenya', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Transform policy into results with PFM Reports Automation Hacks.', 'Convert data and compliance into actionable policy.', 'Rapid prototyping; stakeholder simulations; financial modeling.', 'Make policies actually work—and get noticed for it.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Crisis Command', 'This is your battlefield training. AI alerts, high-speed decisions, and real-time crises push you to lead through chaos. You learn to stay calm when others freeze—and to guide your team when the world feels uncertain. You become the leader people trust with the hardest moments.', 'Monday 23 – Friday 27 November 2026', 'Dubai, UAE', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'Lead under uncertainty – Strategic Analytics with AI Sata Automation.', 'Apply finance, compliance, and analytics in crises.', 'High-stakes crisis simulations; AI alerts; live decisions.', 'Stay calm, act fast, and be the leader everyone trusts.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse'],
    ['Strategist Ascension', 'December is your rise. The capstone pulls everything together—PFM, analytics, governance, and leadership—until you step forward not as a participant, but as a visionary. You leave with a plan, a mentor, and a new identity: the strategist who shapes the future, not just responds to it.', 'Monday 14 – Friday 19 December 2026', 'Singapore', 'Government Accountants, Auditors, Budget Managers, Legislators, Planners', 'From technician to visionary -PFM, Governance, Leadership, Data Analytics & Automation.', 'Synthesize all skills into strategic leadership for public finance governance impact.', 'Capstone: multi-year fiscal planning and forecasting; mentorship.', 'Be the public finance leader everyone wishes they could become.', 'Contact Us for Great Discounts: Workshops | Virtual | Inhouse']
];

$stmt = $conn->prepare("INSERT INTO `events` (`title`, `description`, `dates`, `venue`, `target_audience`, `tagline`, `objective`, `method`, `benefit`, `cost`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($events_data as $event) {
    $stmt->bind_param("ssssssssss",
        $event[0], $event[1], $event[2], $event[3], $event[4],
        $event[5], $event[6], $event[7], $event[8], $event[9]
    );

    if (!$stmt->execute()) {
        echo "Error inserting event: " . $stmt->error . "<br>";
    }
}

$stmt->close();
$conn->close();

echo "<h2>Database setup completed successfully!</h2>";
echo "<p>Tables created and sample data inserted.</p>";
echo "<p><a href='index.php'>View the CPD Events</a></p>";
?>
