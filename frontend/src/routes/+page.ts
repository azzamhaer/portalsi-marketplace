import { apiEndpoints } from '$lib/api';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ fetch }) => {
  try {
    const data = await apiEndpoints.home(fetch);
    return { ...data };
  } catch {
    return { categories: [], flashSale: [], recommended: [], official: [] };
  }
};
