export interface User {
    id: number;
    name: string;
    email: string;
    role: 'founder' | 'retailer' | 'investor';
    phone: string | null;
    account_id: number | null;
    email_verified_at: string | null;
}

export interface Auth {
    user: User | null;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: { success: string | null };
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}
