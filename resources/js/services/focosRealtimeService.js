import { fetchCurrentFocos } from '../interactive-map/services/focosService.js';

export const fetchRealtimeFocos = async ({ bbox, filters, signal }) => {
    return fetchCurrentFocos({ bbox, filters, signal });
};
