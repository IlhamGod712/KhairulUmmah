export interface PengaturanItem {
    kunci: string;
    nilai: string;
    tipe: string;
}

export interface PengaturanMap {
    biaya_spp_bulanan: PengaturanItem;
    spp_tanggal_jatuh_tempo: PengaturanItem;
}
