import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  searchOutline,
  timeOutline,
  documentTextOutline,
  alertCircleOutline,
  idCardOutline,
  personOutline,
  briefcaseOutline,
  cardOutline,
  peopleOutline,
  airplaneOutline,
  businessOutline,
  arrowForwardOutline,
  closeCircleOutline,
  headsetOutline,
  calendarOutline,
  listOutline,
  shieldCheckmarkOutline,
  chevronForwardOutline,
  ribbonOutline,
} from 'ionicons/icons';
import { ProcedureService, ProcedureType } from '../../services/procedure.service';

type FilterKind = 'all' | 'fast' | 'long';

interface QuickAction {
  icon: string;
  key: string;
  route: string;
  accent: string;
}

const CARD_ICONS: string[] = [
  'document-text-outline',
  'id-card-outline',
  'person-outline',
  'briefcase-outline',
  'card-outline',
  'people-outline',
  'airplane-outline',
  'business-outline',
];

const CARD_ACCENTS: string[] = ['is-blue', 'is-green', 'is-orange', 'is-purple'];

@Component({
  selector: 'app-procedures',
  templateUrl: './procedures.page.html',
  styleUrls: ['./procedures.page.scss'],
  standalone: false,
})
export class ProceduresPage implements OnInit {
  public procedures: ProcedureType[] = [];
  public filteredProcedures: ProcedureType[] = [];
  public searchQuery = '';
  public filter: FilterKind = 'all';
  public isLoading = true;
  public error = '';

  public readonly cardIcons = CARD_ICONS;
  public readonly cardAccents = CARD_ACCENTS;
  public readonly skeletonIndexes = [0, 1, 2, 3, 4, 5];

  public readonly quickActions: QuickAction[] = [
    { icon: 'calendar-outline', key: 'procedures.quick_appointments', route: '/appointments', accent: 'is-blue' },
    { icon: 'document-text-outline', key: 'procedures.quick_request', route: '/request-create', accent: 'is-green' },
    { icon: 'search-outline', key: 'procedures.quick_record', route: '/record-search', accent: 'is-orange' },
    { icon: 'list-outline', key: 'procedures.quick_my', route: '/my-procedures', accent: 'is-purple' },
  ];

  constructor(
    private readonly navCtrl: NavController,
    private readonly procedureService: ProcedureService,
  ) {
    addIcons({
      searchOutline,
      timeOutline,
      documentTextOutline,
      alertCircleOutline,
      idCardOutline,
      personOutline,
      briefcaseOutline,
      cardOutline,
      peopleOutline,
      airplaneOutline,
      businessOutline,
      arrowForwardOutline,
      closeCircleOutline,
      headsetOutline,
      calendarOutline,
      listOutline,
      shieldCheckmarkOutline,
      chevronForwardOutline,
      ribbonOutline,
    });
  }

  async ngOnInit(): Promise<void> {
    await this.loadProcedures();
  }

  async loadProcedures(): Promise<void> {
    this.isLoading = true;
    this.error = '';
    try {
      const res = await firstValueFrom(this.procedureService.getAll());
      this.procedures = res.procedures.filter(p => p.isActive);
      this.applyFilters();
    } catch {
      this.error = 'Error al cargar los trámites. Verifica tu conexión.';
    } finally {
      this.isLoading = false;
    }
  }

  onSearch(event: Event): void {
    this.searchQuery = (event.target as HTMLInputElement).value ?? '';
    this.applyFilters();
  }

  setFilter(filter: FilterKind): void {
    this.filter = filter;
    this.applyFilters();
  }

  clearSearch(): void {
    this.searchQuery = '';
    this.applyFilters();
  }

  applyFilters(): void {
    const query = this.searchQuery.trim().toLowerCase();

    let list = this.procedures;
    if (query) {
      list = this.procedures.filter(p =>
        p.name.toLowerCase().includes(query) ||
        (p.description || '').toLowerCase().includes(query)
      );
    }

    if (this.filter === 'fast') {
      list = [...list].sort((a, b) => a.estimatedDays - b.estimatedDays);
    } else if (this.filter === 'long') {
      list = [...list].sort((a, b) => b.estimatedDays - a.estimatedDays);
    }

    this.filteredProcedures = list;
  }

  cardIcon(index: number): string {
    return this.cardIcons[index % this.cardIcons.length];
  }

  cardAccent(index: number): string {
    return this.cardAccents[index % this.cardAccents.length];
  }

  goToDetail(slug: string): void {
    void this.navCtrl.navigateForward(`/procedures/${slug}`);
  }

  goToHelp(): void {
    void this.navCtrl.navigateForward('/help-contact');
  }

  goToQuickAction(action: QuickAction): void {
    void this.navCtrl.navigateForward(action.route);
  }

  async doRefresh(event: any): Promise<void> {
    await this.loadProcedures();
    event.target.complete();
  }
}