import { browser } from '$app/environment';
import { PUBLIC_API_URL } from '$env/static/public';

const BASE = PUBLIC_API_URL || 'http://localhost:8000/api';

export class ApiError extends Error {
  constructor(public status: number, public data: any, message: string) { super(message); }
}

const tokenKey = 'pm_token';
export function getToken(): string | null {
  if (!browser) return null;
  return localStorage.getItem(tokenKey);
}
export function setToken(t: string | null) {
  if (!browser) return;
  if (t) localStorage.setItem(tokenKey, t);
  else   localStorage.removeItem(tokenKey);
}

export async function api<T = any>(
  path: string,
  init: RequestInit & { fetcher?: typeof fetch; auth?: boolean } = {}
): Promise<T> {
  const f = init.fetcher || fetch;
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    ...((init.headers as Record<string, string>) || {})
  };
  if (init.body && !(init.body instanceof FormData) && typeof init.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(init.body);
  }
  const tok = getToken();
  if (tok) headers['Authorization'] = `Bearer ${tok}`;

  const res = await f(`${BASE}${path}`, { ...init, headers });
  const text = await res.text();
  let data: any;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }

  if (!res.ok) {
    const msg = (data && (data.message || data.error)) || `HTTP ${res.status}`;
    throw new ApiError(res.status, data, msg);
  }
  return data as T;
}

export const apiEndpoints = {
  /* settings */
  publicSettings: (f?: typeof fetch) => api('/settings/public', { fetcher: f }),

  /* home & catalog */
  home:        (f?: typeof fetch) => api('/home',           { fetcher: f }),
  products:    (q: string,  f?: typeof fetch) => api('/products' + (q ? '?' + q : ''), { fetcher: f }),
  product:     (id: string|number, f?: typeof fetch) => api(`/products/${id}`, { fetcher: f }),
  categories:  (f?: typeof fetch) => api('/categories', { fetcher: f }),
  tags:        (f?: typeof fetch) => api('/tags', { fetcher: f }),
  vendors:     (q: string = '', f?: typeof fetch) => api('/vendors' + (q ? '?' + q : ''), { fetcher: f }),
  vendor:      (idOrUsername: string|number, f?: typeof fetch) => api(`/vendors/${idOrUsername}`, { fetcher: f }),
  paymentMethods: (f?: typeof fetch) => api('/payment-methods', { fetcher: f }),
  shippingOptions:(f?: typeof fetch) => api('/shipping-options', { fetcher: f }),

  /* auth */
  login:    (email: string, password: string) => api('/auth/login',    { method: 'POST', body: { email, password } as any }),
  register: (b: any) => api('/auth/register', { method: 'POST', body: b }),
  logout:   ()       => api('/auth/logout',  { method: 'POST' }),
  me:       ()       => api('/auth/me'),
  updateProfile: (b: any) => api('/auth/profile', { method: 'PUT', body: b }),

  /* address */
  addresses:       () => api('/addresses'),
  saveAddress:     (b: any) => api('/addresses', { method: 'POST', body: b }),
  updateAddress:   (id: number, b: any) => api(`/addresses/${id}`, { method: 'PUT', body: b }),
  deleteAddress:   (id: number) => api(`/addresses/${id}`, { method: 'DELETE' }),

  /* wishlist */
  getWishlist:    () => api('/wishlist'),
  toggleWishlist: (id: number) => api('/wishlist/toggle', { method: 'POST', body: { product_id: id } as any }),

  /* orders */
  orders:         (q='') => api('/orders' + (q ? '?' + q : '')),
  order:          (id: string|number) => api(`/orders/${id}`),
  checkout:       (b: any)     => api('/checkout', { method: 'POST', body: b }),
  refreshOrder:   (id: string|number) => api(`/orders/${id}/refresh`, { method: 'POST' }),
  simulateOrder:  (id: string|number) => api(`/orders/${id}/simulate`,{ method: 'POST' }),
  markOrderDone:  (id: string|number) => api(`/orders/${id}/done`,    { method: 'POST' }),
  requestReturn:  (id: string|number, reason: string) => api(`/orders/${id}/return`, { method: 'POST', body: { reason } as any }),

  /* seller */
  sellerRegister:   (b: any | FormData) => api('/seller/register', { method: 'POST', body: b }),
  sellerDashboard:  ()         => api('/seller/dashboard'),
  sellerProducts:   ()         => api('/seller/products'),
  sellerCreateProduct: (b: any)=> api('/seller/products', { method: 'POST', body: b }),
  sellerUpdateProduct: (id: number, b: any) => api(`/seller/products/${id}`, { method: 'PUT', body: b }),
  sellerDeleteProduct: (id: number) => api(`/seller/products/${id}`, { method: 'DELETE' }),
  sellerOrders:     ()         => api('/seller/orders'),
  sellerShipOrder:  (id: number) => api(`/seller/orders/${id}/ship`, { method: 'POST' }),
  sellerUpdateProfile:(b: any) => api('/seller/profile', { method: 'PUT', body: b }),
  sellerUpdateUsername:(username: string) => api('/seller/username', { method: 'POST', body: { username } as any }),

  /* reviews */
  canReviewProduct: (productId: number) => api(`/products/${productId}/can-review`),
  submitReview:     (productId: number, rating: number, comment: string) =>
    api(`/products/${productId}/reviews`, { method: 'POST', body: { rating, comment } as any }),
  deleteReview:     (reviewId: number) => api(`/reviews/${reviewId}`, { method: 'DELETE' }),

  /* chat */
  chats:       () => api('/chats'),
  chatThread:  (id: number) => api(`/chats/${id}`),
  startChat:   (vendor_id: number, product_id?: number) => api('/chats', { method: 'POST', body: { vendor_id, product_id } as any }),
  sendMessage: (thread_id: number, message: string) => api(`/chats/${thread_id}/messages`, { method: 'POST', body: { message } as any }),

  /* withdraw seller */
  sellerWithdraw:        () => api('/seller/withdraw'),
  sellerRequestWithdraw: (amount: number) => api('/seller/withdraw', { method: 'POST', body: { amount } as any }),
  sellerCancelWithdraw:  (id: number) => api(`/seller/withdraw/${id}`, { method: 'DELETE' }),

  /* follow */
  toggleFollow: (vendorId: number) => api(`/vendors/${vendorId}/follow`, { method: 'POST' }),
  followStatus: (vendorId: number) => api(`/vendors/${vendorId}/follow`),

  /* admin */
  adminStats:        () => api('/admin/stats'),
  adminUsers:        (q='') => api('/admin/users' + (q ? '?'+q : '')),
  adminDeleteUser:   (id: number) => api(`/admin/users/${id}`, { method: 'DELETE' }),
  adminUpdateUser:   (id: number, b: any) => api(`/admin/users/${id}`, { method: 'PUT', body: b }),
  adminVendors:      (q='') => api('/admin/vendors' + (q ? '?'+q : '')),
  adminVerifyVendor: (id: number, status: 'APPROVED'|'REJECTED', note?: string) => api(`/admin/vendors/${id}/verify`, { method: 'POST', body: { status, note } as any }),
  adminSetVendorBadge: (id: number, badge: string | null) => api(`/admin/vendors/${id}/badge`, { method: 'POST', body: { badge } as any }),
  adminDeleteVendor: (id: number) => api(`/admin/vendors/${id}`, { method: 'DELETE' }),
  adminWithdrawals:  (q='') => api('/admin/withdrawals' + (q ? '?'+q : '')),
  adminProcessWithdraw: (id: number, status: string, admin_note?: string) => api(`/admin/withdrawals/${id}`, { method: 'POST', body: { status, admin_note } as any }),
  adminOrders:       (q='') => api('/admin/orders' + (q ? '?'+q : '')),
  adminUpdateOrder:  (id: number, b: any) => api(`/admin/orders/${id}`, { method: 'PUT', body: b }),
  adminReturns:      () => api('/admin/returns'),
  adminApproveReturn:(id: number, status: string) => api(`/admin/returns/${id}`, { method: 'POST', body: { status } as any }),
  adminSettings:     () => api('/admin/settings'),
  adminSaveSettings: (b: any) => api('/admin/settings', { method: 'PUT', body: b }),
  adminUploadLogo:   (fd: FormData) => api('/admin/settings/logo', { method: 'POST', body: fd as any }),
  adminUploadHero:   (fd: FormData) => api('/admin/settings/hero', { method: 'POST', body: fd as any }),
  adminShipping:     () => api('/admin/shipping-options'),
  adminSaveShipping: (list: any[]) => api('/admin/shipping-options', { method: 'PUT', body: { items: list } as any }),
};
