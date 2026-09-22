<!-- Products & Plans Inventory Management View -->
<?php
$db = getDB();
$products = $db->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id ASC
")->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<div class="page-content">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
      <h1 style="font-size:1.6rem; font-weight:800; color:var(--text-primary);">Products & Service Catalog</h1>
      <p style="font-size:0.85rem; color:var(--text-secondary);">Manage supplements, gear, memberships, and PT stock</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('newProductModal')">
      + Add Product / Plan
    </button>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-mobile-card">
        <thead>
          <tr>
            <th>Barcode</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Type</th>
            <th>Price</th>
            <th>Stock Quantity</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td data-label="Barcode">
                <span style="font-family:var(--font-mono); font-weight:600;"><?= htmlspecialchars($p['barcode'] ?: 'N/A') ?></span>
              </td>
              <td data-label="Item Name">
                <strong style="color:var(--text-primary);"><?= htmlspecialchars($p['name']) ?></strong>
              </td>
              <td data-label="Category">
                <?= htmlspecialchars($p['cat_name']) ?>
              </td>
              <td data-label="Type">
                <span class="badge badge-info"><?= strtoupper($p['type']) ?></span>
              </td>
              <td data-label="Price">
                <strong style="color:var(--primary);">₹<?= number_format($p['price'], 2) ?></strong>
              </td>
              <td data-label="Stock Quantity">
                <?= $p['stock_quantity'] ?> <?= $p['unit'] ?>
              </td>
              <td data-label="Status">
                <span class="badge badge-<?= $p['status'] === 'active' ? 'success' : 'danger' ?>"><?= strtoupper($p['status']) ?></span>
              </td>
              <td data-label="Actions">
                <div style="display:flex; gap:0.4rem;">
                  <button class="btn btn-secondary" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="openEditProduct(<?= htmlspecialchars(json_encode($p)) ?>)">✏️ Edit</button>
                  <button class="btn btn-danger" style="padding:0.25rem 0.5rem; font-size:0.75rem;" onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">🗑️ Delete</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal" id="newProductModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('newProductModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:480px; border-radius:16px; padding:1.5rem;">
    <div class="card-header">
      <span class="card-title">📦 Add New Product / Service</span>
      <button class="modal-close" style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('newProductModal')">✕</button>
    </div>

    <form id="newProductForm" onsubmit="event.preventDefault(); submitCreateProduct();">
      <div class="form-group">
        <label class="form-label">Category</label>
        <select id="npCategory" class="form-control">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" id="npName" class="form-control" required placeholder="e.g. Whey Protein Isolate 1kg">
      </div>

      <div class="form-group">
        <label class="form-label">Type</label>
        <select id="npType" class="form-control">
          <option value="product">Physical Product</option>
          <option value="membership">Membership Plan</option>
          <option value="pt">Personal Training Package</option>
          <option value="service">Service</option>
        </select>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Price (₹) *</label>
          <input type="number" step="0.01" id="npPrice" class="form-control" required placeholder="1499.00">
        </div>
        <div class="form-group">
          <label class="form-label">Stock Quantity</label>
          <input type="number" id="npStock" class="form-control" value="50">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Barcode Number (Optional)</label>
        <input type="text" id="npBarcode" class="form-control" placeholder="Auto-generated if left blank">
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('newProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal" id="editProductModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
  <div class="modal-overlay" style="position:absolute; inset:0;" onclick="closeModal('editProductModal')"></div>
  <div class="card" style="position:relative; z-index:10; background:#fff; width:90%; max-width:480px; border-radius:16px; padding:1.5rem;">
    <div class="card-header">
      <span class="card-title">✏️ Edit Product Details</span>
      <button class="modal-close" style="background:none; border:none; font-size:1.2rem; cursor:pointer;" onclick="closeModal('editProductModal')">✕</button>
    </div>

    <form id="editProductForm" onsubmit="event.preventDefault(); submitEditProduct();">
      <input type="hidden" id="epId">
      <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" id="epName" class="form-control" required>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
        <div class="form-group">
          <label class="form-label">Price (₹) *</label>
          <input type="number" step="0.01" id="epPrice" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Stock Quantity</label>
          <input type="number" id="epStock" class="form-control" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Status</label>
        <select id="epStatus" class="form-control">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.25rem;">
        <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('editProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-block">Update Product</button>
      </div>
    </form>
  </div>
</div>

<script>
function submitCreateProduct() {
  const payload = {
    category_id: document.getElementById('npCategory').value,
    name: document.getElementById('npName').value.trim(),
    type: document.getElementById('npType').value,
    price: document.getElementById('npPrice').value,
    stock_quantity: document.getElementById('npStock').value,
    barcode: document.getElementById('npBarcode').value.trim()
  };

  fetch('api/inventory.php?action=create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast('Product Created Successfully!', 'success');
      closeModal('newProductModal');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(res.message || 'Creation failed', 'danger');
    }
  });
}

function openEditProduct(prod) {
  document.getElementById('epId').value = prod.id;
  document.getElementById('epName').value = prod.name;
  document.getElementById('epPrice').value = prod.price;
  document.getElementById('epStock').value = prod.stock_quantity;
  document.getElementById('epStatus').value = prod.status;
  openModal('editProductModal');
}

function submitEditProduct() {
  const payload = {
    id: document.getElementById('epId').value,
    name: document.getElementById('epName').value.trim(),
    price: document.getElementById('epPrice').value,
    stock_quantity: document.getElementById('epStock').value,
    status: document.getElementById('epStatus').value
  };

  fetch('api/inventory.php?action=update', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast('Product Updated Successfully!', 'success');
      closeModal('editProductModal');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(res.message || 'Update failed', 'danger');
    }
  });
}

function deleteProduct(id, name) {
  if (confirm(`Are you sure you want to delete product: ${name}?`)) {
    fetch(`api/inventory.php?action=delete&id=${id}`)
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          showToast('Product Deleted Successfully!', 'success');
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(res.message || 'Deletion failed', 'danger');
        }
      });
  }
}
</script>
