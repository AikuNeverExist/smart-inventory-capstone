<?php

function ensureSupplierProfile(mysqli $conn, int $userId, string $defaultName): array
{
    $stmt = $conn->prepare(
        "SELECT id, user_id, supplier_name, contact_person, phone, email, address
         FROM suppliers
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($profile) {
        return $profile;
    }

    $supplierName = trim($defaultName) !== '' ? trim($defaultName) : 'Supplier';
    $contactPerson = $supplierName;
    $blank = '';

    $stmt = $conn->prepare(
        "INSERT INTO suppliers
         (user_id, supplier_name, contact_person, phone, email, address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'isssss',
        $userId,
        $supplierName,
        $contactPerson,
        $blank,
        $blank,
        $blank
    );
    $stmt->execute();
    $supplierId = $stmt->insert_id;
    $stmt->close();

    return [
        'id' => $supplierId,
        'user_id' => $userId,
        'supplier_name' => $supplierName,
        'contact_person' => $contactPerson,
        'phone' => '',
        'email' => '',
        'address' => ''
    ];
}
