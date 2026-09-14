import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { ApiConfigService } from './api-config.service';

@Injectable({ providedIn: 'root' })
export class DocumentService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/documents`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  uploadDocument(requestId: number, file: Blob | File, documentType?: string) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('requestId', String(requestId));
    if (documentType) formData.append('documentType', documentType);
    return this.http.post<{ message: string; document: { id: number; name: string; mimeType: string; size: number } }>(
      `${this.apiUrl}/upload`, formData
    );
  }

  downloadDocument(id: number) {
    return this.http.get(`${this.apiUrl}/${id}/download`, { responseType: 'blob' });
  }

  validateDocument(id: number, isValid: boolean) {
    return this.http.patch<{ message: string }>(`${this.apiUrl}/${id}/validate`, { isValid });
  }

  deleteDocument(id: number) {
    return this.http.delete<{ message: string }>(`${this.apiUrl}/${id}`);
  }
}
