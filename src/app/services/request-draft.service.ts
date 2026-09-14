import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

export interface RequestDraft {
  procedureTypeId: number;
  notes: string;
  fileNames: string[];
  updatedAt: string;
}

function draftKey(procedureTypeId: number): string {
  return `occre_request_draft_${procedureTypeId}`;
}

@Injectable({ providedIn: 'root' })
export class RequestDraftService {
  async saveDraft(procedureTypeId: number, notes: string, fileNames: string[]): Promise<void> {
    try {
      const draft: RequestDraft = {
        procedureTypeId,
        notes,
        fileNames,
        updatedAt: new Date().toISOString(),
      };
      await Preferences.set({ key: draftKey(procedureTypeId), value: JSON.stringify(draft) });
    } catch (err) {
      console.warn('[RequestDraft] Error guardando borrador:', err);
    }
  }

  async getDraft(procedureTypeId: number): Promise<RequestDraft | null> {
    try {
      const { value } = await Preferences.get({ key: draftKey(procedureTypeId) });
      return value ? (JSON.parse(value) as RequestDraft) : null;
    } catch {
      return null;
    }
  }

  async clearDraft(procedureTypeId: number): Promise<void> {
    try {
      await Preferences.remove({ key: draftKey(procedureTypeId) });
    } catch (err) {
      console.warn('[RequestDraft] Error limpiando borrador:', err);
    }
  }
}
