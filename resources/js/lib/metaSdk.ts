/**
 * Loads the Facebook JS SDK on demand and launches Meta Embedded Signup (B9.2).
 * The SDK is only loaded when an app id is configured; otherwise the panel shows
 * the manual path. The returned data (code / phone_number_id / waba_id) is posted
 * to the backend, which exchanges it for a token and persists the Channel.
 */

interface FB {
    init(opts: Record<string, unknown>): void;
    login(cb: (res: { authResponse?: { code?: string; accessToken?: string } }) => void, opts: Record<string, unknown>): void;
}

declare global {
    interface Window {
        FB?: FB;
        fbAsyncInit?: () => void;
    }
}

let loading: Promise<void> | null = null;

export function loadFacebookSdk(appId: string, version: string): Promise<void> {
    if (window.FB) return Promise.resolve();
    if (loading) return loading;

    loading = new Promise((resolve) => {
        window.fbAsyncInit = () => {
            window.FB?.init({ appId, autoLogAppEvents: true, xfbml: false, version });
            resolve();
        };
        const s = document.createElement('script');
        s.src = 'https://connect.facebook.net/en_US/sdk.js';
        s.async = true;
        s.defer = true;
        s.crossOrigin = 'anonymous';
        document.body.appendChild(s);
    });

    return loading;
}

export interface EmbeddedResult {
    code?: string;
    access_token?: string;
    phone_number_id?: string;
    waba_id?: string;
}

/** Launch Embedded Signup; resolves with the data to send to the backend. */
export async function launchEmbeddedSignup(
    appId: string,
    version: string,
    configId: string,
): Promise<EmbeddedResult | null> {
    await loadFacebookSdk(appId, version);
    if (!window.FB) return null;

    // The WhatsApp/IG/Messenger session messages (phone_number_id, waba_id) arrive
    // via a window 'message' event during signup; capture the latest here.
    let session: { phone_number_id?: string; waba_id?: string } = {};
    const onMessage = (event: MessageEvent) => {
        if (typeof event.data !== 'string') return;
        try {
            const data = JSON.parse(event.data);
            if (data.type === 'WA_EMBEDDED_SIGNUP' && data.data) {
                session = { phone_number_id: data.data.phone_number_id, waba_id: data.data.waba_id };
            }
        } catch {
            /* ignore non-JSON messages */
        }
    };
    window.addEventListener('message', onMessage);

    return new Promise((resolve) => {
        window.FB!.login(
            (res) => {
                window.removeEventListener('message', onMessage);
                const auth = res.authResponse;
                if (!auth) return resolve(null);
                resolve({ code: auth.code, access_token: auth.accessToken, ...session });
            },
            {
                config_id: configId,
                response_type: 'code',
                override_default_response_type: true,
                scope: 'whatsapp_business_management,whatsapp_business_messaging,business_management',
            },
        );
    });
}
