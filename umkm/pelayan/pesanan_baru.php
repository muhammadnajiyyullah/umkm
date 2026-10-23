<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cek_akses.php';
require_once __DIR__ . '/../config/data.php';
wajib_role(['pelayan', 'admin']);
$user = current_user();
$pdo = db();
$menus = data_menus($pdo);
$tables = data_tables($pdo);
$editId = (int)($_GET['id'] ?? 0);
$editOrder = null;
$initialCart = [];
$initialTable = null;
$initialType = 'dine_in';
$initialNote = '';
$initialPelanggan = '';
if ($editId > 0) {
    $editOrder = data_order($pdo, $editId);
    if ($editOrder && ($editOrder['pelayan_id'] ?? 0) === (int)$user['id'] && in_array($editOrder['status'] ?? '', ['Baru', 'Diterima'], true)) {
        $initialTable = $editOrder['meja_id'] ?? null;
        $initialType = $editOrder['tipe_pesanan'] ?? 'dine_in';
        $initialNote = $editOrder['catatan'] ?? '';
        $initialPelanggan = (string)($editOrder['nama_pelanggan'] ?? '');
        foreach ($editOrder['items'] ?? [] as $item) {
            $initialCart[] = ['menu_id' => (int)$item['menu_id'], 'qty' => (int)$item['qty'], 'catatan_item' => (string)($item['catatan_item'] ?? '')];
        }
    } else {
        set_flash('danger', 'Pesanan tidak dapat diedit pada status saat ini.');
        redirect('pelayan/pesanan_aktif.php');
    }
}
$categories = data_categories($pdo);
$topbarStats = data_dashboard($pdo, 'pelayan', (int)$user['id']);
$activeMenu = 'new-order';
$pageTitle = $editId ? 'Ubah Pesanan' : 'Pesanan Baru';
$pageSubtitle = $editId ? 'Perbarui item sebelum pesanan masuk ke proses dapur.' : 'Pilih meja, pilih menu, dan kirim pesanan ke dapur dalam satu langkah.';
$menuJson = json_encode($menus, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
$cartJson = json_encode($initialCart, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
$editIdJson = json_encode($editId);
$initialTypeJson = json_encode($initialType);
$initialTableJson = json_encode($initialTable);
$initialNoteJson = json_encode($initialNote);
$initialPelangganJson = json_encode($initialPelanggan);
$pageScript = <<<'JS'
<script>
(function(){
  const BTN_LABEL = '__BTN_LABEL__';
  const menuData = __MENU_JSON__;
  const initialCart = __CART_JSON__;
  const editId = __EDIT_ID__;
  let cart = initialCart.map((item)=>Object.assign({}, item));
  let selectedTable = __INITIAL_TABLE__;
  let orderType = __INITIAL_TYPE__;
  const money = (value) => Cafe.money(value);
  const esc = (value) => Cafe.escape(value);
  const menuById = (id) => menuData.find((item) => Number(item.id) === Number(id));
  const selectedTypeInput = document.querySelector('input[name="tipe_pesanan"]:checked');
  const tablePicker = document.getElementById('tablePicker');
  const cartItems = document.getElementById('cartItems');
  const cartCount = document.getElementById('cartCount');
  const cartTotal = document.getElementById('cartTotal');
  const checkout = document.getElementById('checkoutButton');
  const orderNote = document.getElementById('orderNote');
  const customerInput = document.getElementById('customerName');
  if (orderNote) orderNote.value = __INITIAL_NOTE__;
  if (customerInput) customerInput.value = __INITIAL_PELANGGAN__;

  function updateTableVisibility(){
    const dineIn = document.querySelector('input[name="tipe_pesanan"][value="dine_in"]');
    if (tablePicker) tablePicker.classList.toggle('hidden', !dineIn || !dineIn.checked);
  }
  function setTable(id){
    selectedTable = Number(id) || null;
    document.querySelectorAll('[data-table-id]').forEach((card)=>card.classList.toggle('selected',Number(card.dataset.tableId)===selectedTable));
  }
  document.querySelectorAll('[data-table-id]').forEach((card)=>card.addEventListener('click',()=>{ if(!card.disabled) setTable(card.dataset.tableId); }));
  document.querySelectorAll('input[name="tipe_pesanan"]').forEach((input)=>input.addEventListener('change',()=>{ orderType=input.value; if(input.value==='takeaway') selectedTable=null; updateTableVisibility(); renderCart(); }));
  updateTableVisibility();
  if(selectedTable) setTable(selectedTable);

  function addItem(id){
    const menu=menuById(id); if(!menu || !Number(menu.is_tersedia)) return;
    const existing=cart.find((item)=>Number(item.menu_id)===Number(id));
    if(existing) existing.qty=Math.min(99,Number(existing.qty||0)+1);
    else cart.push({menu_id:Number(id),qty:1,catatan_item:''});
    renderCart();
    const card=document.querySelector('[data-menu-id="'+id+'"]'); if(card) card.classList.add('selected');
  }
  function changeQty(id,delta){
    const item=cart.find((row)=>Number(row.menu_id)===Number(id)); if(!item)return;
    item.qty=Math.max(0,Math.min(99,Number(item.qty||0)+delta));
    if(item.qty===0) cart=cart.filter((row)=>Number(row.menu_id)!==Number(id));
    renderCart();
  }
  function renderCart(){
    if(!cartItems)return;
    const count=cart.reduce((sum,item)=>sum+Number(item.qty||0),0);
    const total=cart.reduce((sum,item)=>{const menu=menuById(item.menu_id);return sum+Number(menu?menu.harga:0)*Number(item.qty||0);},0);
    if(cartCount)cartCount.textContent=count;
    if(cartTotal)cartTotal.textContent=money(total);
    if(!cart.length){cartItems.innerHTML='<div class="cart-empty"><div class="cart-empty-icon">'+iconFallback('receipt')+'</div><strong>Keranjang masih kosong</strong><p>Klik menu di sebelah untuk menambahkan item.</p></div>';return;}
    cartItems.innerHTML=cart.map((item)=>{const menu=menuById(item.menu_id);if(!menu)return '';return '<div class="cart-item"><img class="cart-item-thumb" src="'+esc(menu.foto?Cafe.api(menu.foto):'')+'" alt=""><div class="cart-item-copy"><strong>'+esc(menu.nama)+'</strong><small>'+money(menu.harga)+' / item</small><div class="qty-control"><button type="button" data-qty-minus="'+menu.id+'">−</button><span>'+item.qty+'</span><button type="button" data-qty-plus="'+menu.id+'">+</button></div><input class="form-control cart-item-note" data-note-id="'+menu.id+'" value="'+esc(item.catatan_item||'')+'" placeholder="Catatan item (opsional)"></div><strong class="cart-item-price">'+money(menu.harga*item.qty)+'</strong></div>';}).join('');
    document.querySelectorAll('[data-qty-minus]').forEach((b)=>b.addEventListener('click',()=>changeQty(b.dataset.qtyMinus,-1)));
    document.querySelectorAll('[data-qty-plus]').forEach((b)=>b.addEventListener('click',()=>changeQty(b.dataset.qtyPlus,1)));
    document.querySelectorAll('[data-note-id]').forEach((input)=>input.addEventListener('input',()=>{const item=cart.find((row)=>Number(row.menu_id)===Number(input.dataset.noteId));if(item)item.catatan_item=input.value;}));
  }
  function iconFallback(name){return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M8 8h8M8 12h8M8 16h4"/></svg>';}
  document.querySelectorAll('[data-menu-id]').forEach((card)=>card.addEventListener('click',(event)=>{if(event.target.closest('.menu-add')||event.target.closest('button'))return;addItem(card.dataset.menuId);}));
  document.querySelectorAll('.menu-add').forEach((button)=>button.addEventListener('click',(event)=>{event.stopPropagation();addItem(button.dataset.menuId);}));
  document.querySelectorAll('[data-category]').forEach((tab)=>tab.addEventListener('click',()=>{document.querySelectorAll('[data-category]').forEach((item)=>item.classList.remove('active'));tab.classList.add('active');const category=tab.dataset.category;document.querySelectorAll('[data-menu-id]').forEach((card)=>card.hidden=category!=='all'&&card.dataset.category!==category);}));
  const search=document.getElementById('menuSearch'); if(search) search.addEventListener('input',()=>{const query=search.value.toLowerCase().trim();document.querySelectorAll('[data-menu-id]').forEach((card)=>card.hidden=query? !card.textContent.toLowerCase().includes(query):false);});
  renderCart();

  // Notifikasi error TAMPIL DI DALAM panel keranjang, bukan jauh di atas,
  // supaya tidak terlewat saat menekan tombol simpan.
  const cartAlert=document.getElementById('cartAlert');
  function clearCartError(){
    if(cartAlert){cartAlert.textContent='';cartAlert.hidden=true;}
    customerInput?.classList.remove('is-invalid');
  }
  function showCartError(message,focusTarget){
    if(cartAlert){cartAlert.textContent=message;cartAlert.hidden=false;}
    Cafe.notify(message,'danger');
    if(focusTarget){focusTarget.classList.add('is-invalid');focusTarget.focus();focusTarget.scrollIntoView({block:'center',behavior:'smooth'});}
  }
  customerInput?.addEventListener('input',clearCartError);

  checkout?.addEventListener('click',async()=>{
    clearCartError();
    if(!cart.length){showCartError('Keranjang masih kosong. Klik menu di sebelah untuk menambahkan item.');return;}
    const pelanggan=(customerInput?.value||'').trim();
    if(!pelanggan){showCartError('Nama pelanggan wajib diisi sebelum menyimpan pesanan.',customerInput);return;}
    if(orderType==='dine_in'&&!selectedTable){
      showCartError('Pilih nomor meja terlebih dahulu (bagian "Pilih meja" di atas).');
      tablePicker?.scrollIntoView({block:'center',behavior:'smooth'});
      tablePicker?.classList.add('needs-attention');
      window.setTimeout(()=>tablePicker?.classList.remove('needs-attention'),2400);
      return;
    }
    tablePicker?.classList.remove('needs-attention');
    checkout.disabled=true;checkout.innerHTML='<span class="loading-spinner"></span> Mengirim...';
    const params=new URLSearchParams();params.append('csrf_token',Cafe.csrf);params.append('items',JSON.stringify(cart));params.append('tipe_pesanan',orderType);params.append('meja_id',selectedTable||'');params.append('catatan',orderNote?.value||'');params.append('nama_pelanggan',pelanggan);if(editId)params.append('edit_id',editId);
    try{
      const result=await Cafe.request(Cafe.api('api/simpan_pesanan.php'),{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:params.toString()});
      Cafe.notify(result.message||'Pesanan tersimpan.','success');
      setTimeout(()=>{window.location.href=result.redirect||Cafe.api('pelayan/pesanan_aktif.php');},500);
    }catch(error){
      // Selalu kembalikan tombol agar bisa diklik ulang.
      checkout.disabled=false;
      checkout.innerHTML=BTN_LABEL+' <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
      if(/kedaluwarsa/i.test(error.message)){
        if(cartAlert){cartAlert.textContent='Sesi halaman sudah lama. Muat ulang halaman lalu simpan ulang pesanan ini.';cartAlert.hidden=false;}
      }else if(/nama pelanggan/i.test(error.message)){
        showCartError(error.message,customerInput);
      }else if(/meja/i.test(error.message)){
        showCartError(error.message);
      }else{
        if(cartAlert){cartAlert.textContent=error.message;cartAlert.hidden=false;}
        Cafe.notify(error.message,'danger');
      }
    }
  });
}());
</script>
JS;
$pageScript = str_replace(
    ['__MENU_JSON__', '__CART_JSON__', '__EDIT_ID__', '__INITIAL_TABLE__', '__INITIAL_TYPE__', '__INITIAL_NOTE__', '__INITIAL_PELANGGAN__', '__BTN_LABEL__'],
    [$menuJson, $cartJson, $editIdJson, $initialTableJson, $initialTypeJson, $initialNoteJson, $initialPelangganJson, $editId ? 'Simpan perubahan' : 'Kirim ke dapur'],
    $pageScript
);
require __DIR__ . '/../layout/header.php';
?>
<div class="page-heading"><div class="page-heading-copy"><span class="eyebrow">Service flow · 01</span><h1 class="page-title"><?= $editId ? 'Ubah Pesanan' : 'Pesanan Baru' ?></h1><p class="page-subtitle"><?= e($pageSubtitle) ?></p></div><div class="page-actions"><a class="btn btn-ghost" href="<?= e(url('pelayan/pesanan_aktif.php')) ?>"><?= icon('receipt') ?> Pesanan aktif</a><span class="demo-tag"><?= icon('spark') ?> Auto total</span></div></div>
<div class="order-layout">
  <div class="catalog-panel">
    <?php if (!$editId || $initialType === 'dine_in'): ?><section class="card table-picker"><div class="table-picker-head"><div><strong><?= icon('table') ?> Pilih meja</strong><small>Pilih satu meja untuk pesanan makan di tempat</small></div><span class="badge badge-ready">tersedia</span></div><div class="table-grid" id="tablePicker"><?php foreach ($tables as $table): ?><button type="button" class="table-card <?= $table['status'] === 'terisi' ? 'occupied' : '' ?>" data-table-id="<?= (int)$table['id'] ?>" <?= $table['status'] === 'terisi' ? 'disabled' : '' ?>><span><?= e($table['nomor']) ?></span><small><?= $table['status'] === 'terisi' ? 'Terisi' : 'Tersedia' ?></small></button><?php endforeach; ?></div></section><?php endif; ?>
    <div class="catalog-toolbar"><div class="category-tabs"><button class="category-tab active" type="button" data-category="all">Semua</button><?php foreach ($categories as $category): ?><button class="category-tab" type="button" data-category="<?= (int)$category['id'] ?>"><?= e($category['nama']) ?></button><?php endforeach; ?></div><div class="catalog-search search-box"><?= icon('search') ?><input class="form-control" id="menuSearch" type="search" placeholder="Cari menu..."></div></div>
    <div class="catalog-grid">
      <?php foreach ($menus as $menu): ?><article class="menu-card" data-menu-id="<?= (int)$menu['id'] ?>" data-category="<?= (int)$menu['kategori_id'] ?>" data-menu="<?= json_attr($menu) ?>"><div class="menu-image"><img src="<?= e(image_url($menu['foto'])) ?>" alt="<?= e($menu['nama']) ?>"><span class="menu-type-badge <?= $menu['tipe'] === 'umkm' ? 'umkm' : '' ?>"><?= $menu['tipe'] === 'umkm' ? 'UMKM' : 'CAFE' ?></span><button class="menu-add" type="button" data-menu-id="<?= (int)$menu['id'] ?>" aria-label="Tambah <?= e($menu['nama']) ?>"><?= icon('plus') ?></button></div><div class="menu-info"><div class="menu-name-row"><span class="menu-name"><?= e($menu['nama']) ?></span><span class="menu-stock"><?= (int)$menu['stok'] ?> pcs</span></div><p class="menu-description"><?= e($menu['deskripsi'] ?? '') ?></p><div class="menu-price"><?= e(money($menu['harga'])) ?></div></div></article><?php endforeach; ?>
    </div>
  </div>
  <aside class="card cart-panel"><div class="cart-header"><div><h3>Keranjang</h3><p><?= $editId ? 'Perbarui pesanan #' . e((string)$editOrder['kode_pesanan']) : 'Pesanan untuk #' . e((string)date('Ymd')) ?></p></div><span class="cart-count" id="cartCount">0</span></div><div class="cart-customer"><label class="customer-field-label" for="customerName"><?= icon('user') ?> Nama pelanggan</label><input class="form-control" id="customerName" type="text" maxlength="80" autocomplete="off" placeholder="Wajib diisi, mis. Bu Sari"><small class="customer-field-hint">Ditampilkan ke dapur dan kasir agar pesanan tidak tertukar.</small></div><div class="cart-body" id="cartItems"></div><div class="cart-footer"><textarea class="form-control cart-note" id="orderNote" placeholder="Catatan umum untuk dapur (opsional)"></textarea><div class="order-type-row"><span class="order-type-label">Jenis pesanan</span><label class="radio-pill"><input type="radio" name="tipe_pesanan" value="dine_in" <?= $initialType === 'dine_in' ? 'checked' : '' ?>><span>Dine in</span></label><label class="radio-pill"><input type="radio" name="tipe_pesanan" value="takeaway" <?= $initialType === 'takeaway' ? 'checked' : '' ?>><span>Takeaway</span></label></div><div class="cart-total"><span>Total pesanan</span><strong id="cartTotal">Rp0</strong></div><div class="cart-alert" id="cartAlert" hidden></div><button class="btn btn-primary cart-submit" id="checkoutButton" type="button"><?= $editId ? 'Simpan perubahan' : 'Kirim ke dapur' ?> <?= icon('arrow-right') ?></button></div></aside>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
