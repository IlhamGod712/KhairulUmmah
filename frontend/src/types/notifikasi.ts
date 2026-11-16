export interface NotifikasiItem {
    id: number;
    tipe: string;
    judul: string;
    pesan: string;
    url: string | null;
    data: any;
    dibaca: boolean;
    dibaca_pada: string | null;
    created_at: string | null;
}

export interface NotifikasiListResponse {
    items: NotifikasiItem[];
    unread_count: number;
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
}
