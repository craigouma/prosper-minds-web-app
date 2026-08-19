<?php
require_once 'config.php';

$conn = getDBConnection();

$events = [
    [
        'title' => 'Smart PFM & IPSAS Future Ready Finance Leaders Course',
        'dates' => '19–23 October 2026',
        'venue' => 'Cape Town, South Africa',
        'description' => "Brief: PFM, Data Analytics & Government Automation for Leaders Who Must Deliver\n\nEarly Bird Discounts – Register Early & Save\n20% Discount: Register by 19 July 2026\n15% Discount: Register by 19 August 2026\n10% Discount: Register by 19 September 2026\nThe smartest public finance leaders lock in savings first.\n\nMarketing Messages:\n- Seats will go fast—Africa's serious finance leaders are already planning ahead.\n- When budgets tighten, top leaders sharpen skills.\n- Those who move first will lead first.\n- Be in the room where Africa's next finance wins are shaped.",
        'target_audience' => 'Government Officers, Finance Leaders',
        'tagline' => 'PFM, Data Analytics & Government Automation for Leaders Who Must Deliver',
        'objective' => 'Smart PFM & IPSAS Future Ready Finance Leaders',
        'method' => 'Course',
        'benefit' => 'Save More. Learn More. Lead More.',
        'cost' => 'USD 599 Per Delegate (Excludes Travel and Accommodation) – See Early Bird Discounts'
    ],
    [
        'title' => 'IPSAS Success & Clean Audit Compliance Training',
        'dates' => '16–20 November 2026',
        'venue' => 'Kuala Lumpur, Malaysia',
        'description' => "Brief: IPSAS Compliance & Zero-Failure Reporting for Government Officers\n\nEarly Bird Discounts – Register Early & Save\n20% Discount: Register by 16 August 2026\n15% Discount: Register by 16 September 2026\n10% Discount: Register by 16 October 2026\nStronger compliance starts with early action.\n\nMarketing Messages:\n- Seats will go fast—Africa's serious finance leaders are already planning ahead.\n- When budgets tighten, top leaders sharpen skills.\n- Those who move first will lead first.\n- Be in the room where Africa's next finance wins are shaped.",
        'target_audience' => 'Government Officers',
        'tagline' => 'IPSAS Compliance & Zero-Failure Reporting for Government Officers',
        'objective' => 'IPSAS Success & Clean Audit Compliance',
        'method' => 'Training',
        'benefit' => 'Save More. Learn More. Lead More.',
        'cost' => 'USD 599 Per Delegate (Excludes Travel and Accommodation) – See Early Bird Discounts'
    ],
    [
        'title' => 'Budget Control, Revenue Growth & PFM Funding Breakthrough Conference',
        'dates' => '7–11 December 2026',
        'venue' => 'Bali, Indonesia',
        'description' => "Brief: Cash Control, IPSAS Reporting & Funding Strategies in Tough Times\n\nEarly Bird Discounts – Register Early & Save\n20% Discount: Register by 7 September 2026\n15% Discount: Register by 7 October 2026\n10% Discount: Register by 7 November 2026\nThe best budget leaders prepare before pressure hits.\n\nMarketing Messages:\n- Seats will go fast—Africa's serious finance leaders are already planning ahead.\n- When budgets tighten, top leaders sharpen skills.\n- Those who move first will lead first.\n- Be in the room where Africa's next finance wins are shaped.",
        'target_audience' => 'Budget Leaders',
        'tagline' => 'Cash Control, IPSAS Reporting & Funding Strategies in Tough Times',
        'objective' => 'Budget Control, Revenue Growth & PFM Funding Breakthrough',
        'method' => 'Conference',
        'benefit' => 'Save More. Learn More. Lead More.',
        'cost' => 'USD 599 Per Delegate (Excludes Travel and Accommodation) – See Early Bird Discounts'
    ]
];

$stmt = $conn->prepare("INSERT INTO events (title, description, dates, venue, target_audience, tagline, objective, method, benefit, cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($events as $event) {
    $stmt->bind_param("ssssssssss", 
        $event['title'], 
        $event['description'], 
        $event['dates'], 
        $event['venue'], 
        $event['target_audience'], 
        $event['tagline'], 
        $event['objective'], 
        $event['method'], 
        $event['benefit'], 
        $event['cost']
    );
    $stmt->execute();
}

echo "Events added successfully!";
$conn->close();
?>
