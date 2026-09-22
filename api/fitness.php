<?php
/**
 * Workout, Diet & Body Measurement Progress API
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = getDB();

if ($action === 'exercises') {
    $stmt = $db->query("SELECT * FROM exercises ORDER BY id ASC");
    jsonResponse(true, $stmt->fetchAll(), 'Exercises fetched');

} elseif ($action === 'add_exercise') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $name = trim($input['name'] ?? '');
    $group = $input['muscle_group'] ?? 'Chest';
    $equip = trim($input['equipment'] ?? 'Dumbbell');
    $inst = trim($input['instructions'] ?? '');
    $diff = $input['difficulty'] ?? 'Beginner';

    if (empty($name)) {
        jsonResponse(false, [], 'Exercise name is required', 400);
    }

    $stmt = $db->prepare("INSERT INTO exercises (name, muscle_group, equipment, instructions, difficulty) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $group, $equip, $inst, $diff]);
    
    logAuditAction(1, 'Add Exercise', 'WORKOUT', null, ['name' => $name]);
    jsonResponse(true, ['id' => $db->lastInsertId()], 'New exercise added to library');

} elseif ($action === 'save_workout_plan') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $exerciseId = intval($input['exercise_id'] ?? 0);
    $day = $input['day_of_week'] ?? 'Monday';
    $sets = intval($input['sets'] ?? 3);
    $reps = trim($input['reps'] ?? '10-12');
    $weight = floatval($input['weight_kg'] ?? 0);
    $rest = intval($input['rest_time_sec'] ?? 60);

    if ($memberId <= 0 || $exerciseId <= 0) {
        jsonResponse(false, [], 'Member ID and Exercise selection required', 400);
    }

    $stmt = $db->prepare("INSERT INTO workout_plans (member_id, exercise_id, day_of_week, sets, reps, weight_kg, rest_time_sec) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$memberId, $exerciseId, $day, $sets, $reps, $weight, $rest]);

    logAuditAction(1, 'Assign Workout Plan Item', 'WORKOUT', null, ['member_id' => $memberId, 'day' => $day]);
    jsonResponse(true, [], 'Workout plan item saved successfully');

} elseif ($action === 'save_diet_plan') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $meal = $input['meal_type'] ?? 'Breakfast';
    $items = trim($input['food_items'] ?? '');
    $cals = intval($input['calories'] ?? 0);
    $prot = floatval($input['protein_g'] ?? 0);
    $carbs = floatval($input['carbs_g'] ?? 0);
    $fats = floatval($input['fats_g'] ?? 0);
    $water = floatval($input['water_intake_liters'] ?? 3.5);
    $restrictions = trim($input['food_restrictions'] ?? 'High Protein');
    $notes = trim($input['trainer_notes'] ?? '');

    if ($memberId <= 0 || empty($items)) {
        jsonResponse(false, [], 'Member ID and food items required', 400);
    }

    $stmt = $db->prepare("INSERT INTO diet_plans (member_id, meal_type, food_items, calories, protein_g, carbs_g, fats_g, water_intake_liters, food_restrictions, trainer_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$memberId, $meal, $items, $cals, $prot, $carbs, $fats, $water, $restrictions, $notes]);

    logAuditAction(1, 'Save Diet Plan', 'DIET', null, ['member_id' => $memberId, 'meal' => $meal]);
    jsonResponse(true, [], 'Diet plan meal assigned successfully');

} elseif ($action === 'log_body_measurement') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $memberId = intval($input['member_id'] ?? 0);
    $weight = floatval($input['weight_kg'] ?? 0);
    $height = floatval($input['height_cm'] ?? 0);
    $fat = floatval($input['body_fat_pct'] ?? 0);
    $chest = floatval($input['chest_in'] ?? 0);
    $waist = floatval($input['waist_in'] ?? 0);
    $arms = floatval($input['arms_in'] ?? 0);

    if ($memberId <= 0 || $weight <= 0) {
        jsonResponse(false, [], 'Valid member ID and weight required', 400);
    }

    // Auto-calculate BMI: weight_kg / (height_m ^ 2)
    $bmi = 0;
    if ($height > 0) {
        $heightM = $height / 100;
        $bmi = round($weight / ($heightM * $heightM), 1);
    }

    $stmt = $db->prepare("INSERT INTO body_measurements (member_id, record_date, weight_kg, height_cm, bmi, body_fat_pct, chest_in, waist_in, arms_in) VALUES (?, CURRENT_DATE(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$memberId, $weight, $height, $bmi, $fat, $chest, $waist, $arms]);

    logAuditAction(1, 'Log Body Measurements', 'PROGRESS', null, ['member_id' => $memberId, 'weight' => $weight, 'bmi' => $bmi]);
    jsonResponse(true, ['bmi' => $bmi], "Body progress measurements recorded successfully! Calculated BMI: {$bmi}");

} else {
    jsonResponse(false, [], 'Invalid action', 400);
}
