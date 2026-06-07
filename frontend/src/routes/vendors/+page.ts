import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';
export const load: PageLoad = async ({ url, fetch }) => {
  const f = url.searchParams.get('f') ?? 'all';
  try {
    const data: any = await apiEndpoints.vendors(`filter=${f}`, fetch);
    return { vendors: data, filter: f };
  } catch {
    return { vendors: [], filter: f };
  }
};
