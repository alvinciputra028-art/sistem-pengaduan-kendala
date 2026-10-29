<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Laporan</title>
</head>
<body>
    <main>
        <p><a href="{{ route('reports.index') }}">Kembali ke laporan</a></p>
        <h1>Buat Laporan</h1>
        @if ($errors->any())
            <div role="alert" aria-live="assertive">
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form id="report-form" method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data">
            @csrf
            <p>Gedung: <strong>{{ $building->name }}</strong></p>
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <br>
            <label for="title">Judul</label>
            <input id="title" name="title" value="{{ old('title') }}" maxlength="255" required>
            <br>
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" required>{{ old('description') }}</textarea>
            <br>
            <label for="location_type">Jenis lokasi</label>
            <select id="location_type" name="location_type" required>
                <option value="room" @selected(old('location_type', 'room') === 'room')>Kamar</option>
                <option value="common_area" @selected(old('location_type') === 'common_area')>Area bersama</option>
                <option value="building" @selected(old('location_type') === 'building')>Gedung</option>
            </select>
            <br>
            <div id="room-location">
                <label for="room_id">Kamar</label>
                @if ($room)
                    <input type="hidden" id="room_id" name="room_id" value="{{ $room->id }}">
                    <strong>{{ $room->room_number }}</strong>
                @else
                    <p role="alert">Akun Anda belum memiliki kamar. Lokasi kamar tidak dapat dipilih.</p>
                @endif
            </div>
            <br>
            <div id="location-description-field" hidden>
                <label for="location_description">Keterangan lokasi</label>
                <input id="location_description" name="location_description" value="{{ old('location_description') }}" maxlength="255">
                <small>Contoh: lorong lantai 3 dekat tangga.</small>
                <p id="location-description-error" role="alert" hidden></p>
            </div>
            <br>
            <label for="visibility">Visibilitas</label>
            <select id="visibility" name="visibility" required>
                <option value="participants">Peserta laporan</option>
                <option value="public_building" @selected(old('visibility') === 'public_building')>Publik di gedung</option>
            </select>
            <small id="visibility-help">Laporan kamar otomatis hanya dapat dilihat oleh peserta terkait dan manajer.</small>
            <br>
            <label for="identity_visibility">Identitas pelapor</label>
            <select id="identity_visibility" name="identity_visibility" required>
                <option value="visible">Tampilkan</option>
                <option value="anonymous" @selected(old('identity_visibility') === 'anonymous')>Samarkan</option>
            </select>
            <fieldset>
                <legend>Informasi kondisi</legend>
                @foreach ([
                    'affects_many' => 'Apakah masalah memengaruhi banyak penghuni?',
                    'unusable' => 'Apakah fasilitas tidak dapat digunakan sama sekali?',
                    'safety_risk' => 'Apakah ada risiko keselamatan?',
                    'worsening' => 'Apakah masalah semakin parah?',
                    'ongoing' => 'Apakah masalah terjadi terus-menerus?',
                ] as $name => $label)
                    <label for="assessment_{{ $name }}">{{ $label }}</label>
                    <select id="assessment_{{ $name }}" name="assessment_answers[{{ $name }}]" required>
                        @foreach (['yes' => 'Ya', 'no' => 'Tidak', 'uncertain' => 'Tidak yakin'] as $value => $text)
                            <option value="{{ $value }}" @selected(old("assessment_answers.$name") === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <br>
                @endforeach
                <label for="incident_duration">Sejak kapan masalah terjadi?</label>
                <select id="incident_duration" name="incident_duration" required>
                    @foreach (['today' => 'Hari ini', '1_to_3_days' => '1–3 hari', '4_to_7_days' => '4–7 hari', 'over_7_days' => 'Lebih dari 7 hari'] as $value => $text)
                        <option value="{{ $value }}" @selected(old('incident_duration') === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </fieldset>
            <label for="attachments">Bukti gambar (maks. 5, 10 MB per file)</label>
            <input id="attachments" type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple>
            <p id="attachments-error" role="alert" hidden></p>
            <ul id="attachment-list"></ul>
            <br>
            <button type="submit">Kirim laporan</button>
        </form>
    </main>
    <script>
        const locationType = document.getElementById('location_type');
        const roomLocation = document.getElementById('room-location');
        const locationDescription = document.getElementById('location-description-field');
        const visibility = document.getElementById('visibility');
        const visibilityHelp = document.getElementById('visibility-help');
        const roomInput = document.getElementById('room_id');
        const locationDescriptionInput = document.getElementById('location_description');
        const locationDescriptionError = document.getElementById('location-description-error');
        const attachments = document.getElementById('attachments');
        const attachmentsError = document.getElementById('attachments-error');
        const reportForm = document.getElementById('report-form');
        const maxAttachmentSize = 10 * 1024 * 1024;
        const attachmentList = document.getElementById('attachment-list');

        function updateLocationFields() {
            const isRoom = locationType.value === 'room';
            roomLocation.hidden = !isRoom;
            if (roomInput) {
                roomInput.disabled = !isRoom;
            }
            locationDescription.hidden = isRoom;
            locationDescriptionInput.required = !isRoom;
            visibilityHelp.textContent = isRoom
                ? 'Laporan kamar otomatis hanya dapat dilihat oleh peserta terkait dan manajer.'
                : 'Publik di gedung dapat dilihat penghuni lain di gedung yang sama.';
            if (isRoom) {
                visibility.value = 'participants';
            }
        }

        locationType.addEventListener('change', updateLocationFields);
        function validateLocationDescription() {
            const invalid = !locationDescription.hidden && locationDescriptionInput.value.trim() === '';
            locationDescriptionError.textContent = invalid ? 'Keterangan lokasi wajib diisi untuk area bersama atau gedung.' : '';
            locationDescriptionError.hidden = !invalid;
            locationDescriptionInput.setCustomValidity(invalid ? 'Keterangan lokasi wajib diisi.' : '');
            return !invalid;
        }

        function validateAttachments() {
            const files = [...attachments.files];
            let message = '';
            if (files.length > 5) {
                message = 'Maksimal 5 lampiran dapat dipilih.';
            } else if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) {
                message = 'Format lampiran harus JPG, JPEG, PNG, atau WEBP.';
            } else if (files.some((file) => file.size > maxAttachmentSize)) {
                message = 'Setiap gambar harus berukuran maksimal 10 MB.';
            }
            attachmentsError.textContent = message;
            attachmentsError.hidden = !message;
            attachments.setCustomValidity(message);
            return !message;
        }

        function renderAttachmentList() {
            attachmentList.replaceChildren();
            [...attachments.files].forEach((file, index) => {
                const item = document.createElement('li');
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '−';
                remove.setAttribute('aria-label', `Hapus ${file.name}`);
                remove.addEventListener('click', () => {
                    const dataTransfer = new DataTransfer();
                    [...attachments.files].forEach((current, currentIndex) => {
                        if (currentIndex !== index) dataTransfer.items.add(current);
                    });
                    attachments.files = dataTransfer.files;
                    renderAttachmentList();
                    validateAttachments();
                });
                item.append(document.createTextNode(file.name + ' '), remove);
                attachmentList.append(item);
            });
        }

        locationDescriptionInput.addEventListener('input', validateLocationDescription);
        attachments.addEventListener('change', () => {
            renderAttachmentList();
            validateAttachments();
        });
        reportForm.addEventListener('submit', (event) => {
            if (!validateLocationDescription() || !validateAttachments()) {
                event.preventDefault();
            }
        });
        updateLocationFields();
    </script>
</body>
</html>
