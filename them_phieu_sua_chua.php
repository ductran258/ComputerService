                        rows="3"
                        placeholder="Nhập địa chỉ khách hàng"
                    ></textarea>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="modal-cancel-button"
                    onclick="closeCustomerModal()"
                >
                    Hủy
                </button>

                <button
                    type="submit"
                    class="modal-save-button"
                    id="saveCustomerButton"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu khách hàng
                </button>

            </div>

        </form>

    </div>

</div>

<!-- MODAL THÊM THIẾT BỊ -->

<div class="modal-overlay" id="deviceModal">

    <div class="customer-modal">

        <div class="modal-header">

            <div>
                <h3>
                    <i class="fa-solid fa-laptop-medical"></i>
                    Thêm thiết bị mới
                </h3>
                <p>Thiết bị sẽ được liên kết với khách hàng đang chọn.</p>
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeDeviceModal()"
                aria-label="Đóng"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <form id="addDeviceForm">

            <div class="modal-body">

                <div id="deviceModalMessage" class="modal-message"></div>

                <div class="form-group">
                    <label for="new_device_type">
                        Loại thiết bị <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="new_device_type"
                        name="new_device_type"
                        maxlength="50"
                        placeholder="Ví dụ: Laptop, Máy tính bàn, Máy in"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="new_device_brand">Hãng sản xuất</label>
                    <input
                        type="text"
                        id="new_device_brand"
                        name="new_device_brand"
                        maxlength="100"
                        placeholder="Ví dụ: Dell, HP, Lenovo"
                    >
                </div>

                <div class="form-group">
                    <label for="new_device_model">Model</label>
                    <input
                        type="text"
                        id="new_device_model"
                        name="new_device_model"
                        maxlength="100"
                        placeholder="Nhập model thiết bị"
                    >
                </div>

                <div class="form-group">
                    <label for="new_device_serial">Số serial</label>
                    <input
                        type="text"
                        id="new_device_serial"
                        name="new_device_serial"
                        maxlength="100"
                        placeholder="Nhập số serial nếu có"
                    >
                </div>

                <div class="form-group">
                    <label for="new_device_description">Mô tả</label>
                    <textarea
                        id="new_device_description"
                        name="new_device_description"
                        rows="3"
                        placeholder="Thông tin bổ sung về thiết bị (nếu có)"
                    ></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="modal-cancel-button"
                    onclick="closeDeviceModal()"
                >Hủy</button>

                <button
                    type="submit"
                    class="modal-save-button"
                    id="saveDeviceButton"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Lưu thiết bị
                </button>
            </div>

        </form>

    </div>

</div>

<script>
    const customerSelect = document.getElementById('customer_id');
    const deviceSelect = document.getElementById('device_id');

    // =================================================
    // LỌC THIẾT BỊ THEO KHÁCH HÀNG
    // =================================================

    const submitTicketButton = document.querySelector('.submit-button');
    const openDeviceModalButton = document.getElementById('openDeviceModalButton');

    function updateTicketButton() {
        const selectedOption = deviceSelect.options[deviceSelect.selectedIndex];
        const validCustomer = customerSelect.value !== '';
        const validDevice = selectedOption &&
            selectedOption.value !== '' &&
            selectedOption.dataset.customer === customerSelect.value &&
            !selectedOption.hidden;

        submitTicketButton.disabled = !(validCustomer && validDevice);
        openDeviceModalButton.disabled = !validCustomer;
        openDeviceModalButton.title = validCustomer
            ? 'Thêm thiết bị cho khách hàng đang chọn'
            : 'Chọn khách hàng trước';
    }

    function filterDevices() {
        const selectedCustomer = customerSelect.value;
        const options = deviceSelect.querySelectorAll('option');

        let selectedDeviceStillValid = false;

        options.forEach(function(option) {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const belongsToCustomer = option.dataset.customer === selectedCustomer;
            option.hidden = !belongsToCustomer;

            if (belongsToCustomer && option.value === deviceSelect.value) {
                selectedDeviceStillValid = true;
            }
        });

        if (!selectedDeviceStillValid) {
            deviceSelect.value = '';
        }

        updateTicketButton();
    }

    customerSelect.addEventListener('change', filterDevices);
    deviceSelect.addEventListener('change', updateTicketButton);

    filterDevices();

    // =================================================
    // MỞ / ĐÓNG MODAL KHÁCH HÀNG
    // =================================================

    const customerModal = document.getElementById('customerModal');
    const addCustomerForm = document.getElementById('addCustomerForm');
    const customerModalMessage = document.getElementById('customerModalMessage');
    const saveCustomerButton = document.getElementById('saveCustomerButton');

    function openCustomerModal() {
        customerModal.classList.add('show');
        customerModalMessage.className = 'modal-message';
        customerModalMessage.textContent = '';

        setTimeout(function() {
            document.getElementById('new_customer_name').focus();
        }, 100);
    }

    function closeCustomerModal() {
        customerModal.classList.remove('show');
    }

    // Đóng modal khi nhấn ra bên ngoài
    customerModal.addEventListener('click', function(event) {
        if (event.target === customerModal) {
            closeCustomerModal();
        }
    });

    // Đóng modal bằng phím Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeCustomerModal();
            closeDeviceModal();
        }
    });

    // =================================================
    // GỬI DỮ LIỆU THÊM KHÁCH HÀNG BẰNG FETCH
    // =================================================

    addCustomerForm.addEventListener('submit', async function(event) {

        event.preventDefault();

        customerModalMessage.className = 'modal-message';
        customerModalMessage.textContent = '';

        saveCustomerButton.disabled = true;
        saveCustomerButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        const formData = new FormData(addCustomerForm);

        formData.append('action', 'add_customer');
        formData.append(
            'csrf_token',
            <?php echo json_encode($_SESSION['csrf_token']); ?>
        );

        try {

            const response = await fetch('them_phieu_sua_chua.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                customerModalMessage.className = 'modal-message error';
                customerModalMessage.textContent = result.message;
                return;
            }

            const customer = result.customer;

            // Tạo option mới cho khách hàng vừa thêm
            const newOption = document.createElement('option');

            newOption.value = customer.id;
            newOption.textContent =
                customer.full_name + ' - ' + customer.phone;

            // Thêm vào danh sách khách hàng
            customerSelect.appendChild(newOption);

            // Tự động chọn khách hàng vừa tạo
            customerSelect.value = customer.id;

            // Lọc lại danh sách thiết bị
            filterDevices();

            // Thông báo thành công
            customerModalMessage.className = 'modal-message success';
            customerModalMessage.textContent =
                'Đã thêm khách hàng thành công và tự động chọn khách hàng này.';

            // Làm trống form modal
            addCustomerForm.reset();

            // Đóng modal sau một khoảng ngắn
            setTimeout(function() {
                closeCustomerModal();
            }, 900);

        } catch (error) {

            customerModalMessage.className = 'modal-message error';
            customerModalMessage.textContent =
                'Không thể kết nối máy chủ. Vui lòng thử lại.';

        } finally {

            saveCustomerButton.disabled = false;
            saveCustomerButton.innerHTML =
                '<i class="fa-solid fa-floppy-disk"></i> Lưu khách hàng';

        }

    });

    // =================================================
    // MỞ / ĐÓNG MODAL THIẾT BỊ
    // =================================================

    const deviceModal = document.getElementById('deviceModal');
    const addDeviceForm = document.getElementById('addDeviceForm');
    const deviceModalMessage = document.getElementById('deviceModalMessage');
    const saveDeviceButton = document.getElementById('saveDeviceButton');

    function openDeviceModal() {
        if (!customerSelect.value) {
            return;
        }

        deviceModal.classList.add('show');
        deviceModalMessage.className = 'modal-message';
        deviceModalMessage.textContent = '';

        setTimeout(function() {
            document.getElementById('new_device_type').focus();
        }, 100);
    }

    function closeDeviceModal() {
        deviceModal.classList.remove('show');
    }

    deviceModal.addEventListener('click', function(event) {
        if (event.target === deviceModal) {
            closeDeviceModal();
        }
    });

    // =================================================
    // GỬI DỮ LIỆU THÊM THIẾT BỊ BẰNG FETCH
    // =================================================

    addDeviceForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        deviceModalMessage.className = 'modal-message';
        deviceModalMessage.textContent = '';

        saveDeviceButton.disabled = true;
        saveDeviceButton.innerHTML =
            '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

        const formData = new FormData(addDeviceForm);
        formData.append('action', 'add_device');
        formData.append('new_device_customer_id', customerSelect.value);
        formData.append(
            'csrf_token',
            <?php echo json_encode($_SESSION['csrf_token']); ?>
        );

        try {
            const response = await fetch('them_phieu_sua_chua.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                deviceModalMessage.className = 'modal-message error';
                deviceModalMessage.textContent = result.message;
                return;
            }

            const device = result.device;
            const newOption = document.createElement('option');
            const deviceName = [device.device_type, device.brand, device.model]
                .filter(function(value) { return value && value.trim() !== ''; })
                .join(' ');

            newOption.value = device.id;
            newOption.dataset.customer = device.customer_id;
            newOption.textContent = deviceName +
                (device.serial_number ? ' - SN: ' + device.serial_number : '');

            deviceSelect.appendChild(newOption);
            deviceSelect.value = String(device.id);

            filterDevices();
            updateTicketButton();

            deviceModalMessage.className = 'modal-message success';
            deviceModalMessage.textContent =
                'Đã thêm thiết bị thành công và tự động chọn thiết bị này.';

            addDeviceForm.reset();

            setTimeout(function() {
                closeDeviceModal();
            }, 900);

        } catch (error) {
            deviceModalMessage.className = 'modal-message error';
            deviceModalMessage.textContent =
                'Không thể kết nối máy chủ. Vui lòng thử lại.';
        } finally {
            saveDeviceButton.disabled = false;
            saveDeviceButton.innerHTML =
                '<i class="fa-solid fa-floppy-disk"></i> Lưu thiết bị';
        }
    });
</script>

</body>
</html>