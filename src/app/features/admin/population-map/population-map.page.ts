import { Component, OnInit, OnDestroy, AfterViewInit, HostListener, ElementRef, ViewChild } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminService } from '../admin.service';
import { AuthService } from '../../../services/auth.service';
import type * as L from 'leaflet';

interface PopulationZone {
  id: number;
  name: string;
  zone_type: string;
  latitude: number;
  longitude: number;
  total_population: number;
  raizal_population: number;
  other_population: number;
  color: string;
  description: string;
}

interface ZoneTotals {
  total: number;
  raizal: number;
  other: number;
  zones: number;
}

@Component({
  selector: 'app-population-map',
  templateUrl: './population-map.page.html',
  styleUrls: ['./population-map.page.scss'],
  standalone: false,
})
export class PopulationMapPage implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('mapContainer') mapContainer!: ElementRef<HTMLDivElement>;

  zones: PopulationZone[] = [];
  totals: ZoneTotals | null = null;
  isLoading = true;
  error = '';

  selectedZone: PopulationZone | null = null;
  filter = 'todos';
  zoom = 12;

  private map: L.Map | null = null;
  private circles: L.CircleMarker[] = [];
  private destinyMarker: L.CircleMarker | null = null;
  private leafletModule: typeof import('leaflet') | null = null;
  private initAttempts = 0;

  constructor(
    private readonly adminService: AdminService,
    public readonly authService: AuthService,
  ) {}

  async ngOnInit(): Promise<void> {
    await this.loadData();
  }

  ngAfterViewInit(): void {
    this.mountMap();
  }

  private async loadData(): Promise<void> {
    this.isLoading = true;
    this.error = '';
    try {
      const res = await firstValueFrom(this.adminService.getPopulationMap());
      this.zones = res?.zones || [];
      this.totals = res?.totals || null;
      this.mountMap();
    } catch {
      this.error = 'No fue posible cargar los datos del mapa de población.';
    } finally {
      this.isLoading = false;
    }
  }

  @HostListener('window:resize')
  onResize(): void {
    this.map?.invalidateSize();
  }

  /**
   * Inicializa el mapa en cuanto el contenedor #mapContainer existe y está
   * en el DOM con dimensiones (Leaflet falla si el div tiene alto 0).
   */
  private mountMap(): void {
    if (this.map || this.initAttempts > 80) return;

    this.initAttempts++;
    if (!this.mapContainer) {
      setTimeout(() => this.mountMap(), 50);
      return;
    }

    const el = this.mapContainer.nativeElement;
    if (!el.offsetHeight) {
      setTimeout(() => this.mountMap(), 50);
      return;
    }

    this.initMap();
  }

  private async initMap(): Promise<void> {
    if (this.map || !this.mapContainer) return;

    const L = await this.loadLeaflet();
    this.leafletModule = L;

    this.map = L.map(this.mapContainer.nativeElement, {
      center: [12.556, -81.7075],
      zoom: this.zoom,
      zoomControl: true,
      attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(this.map);

    this.renderCircles(L);

    this.map.on('moveend', () => {
      this.zoom = this.map?.getZoom() ?? 12;
    });

    // Ajusta el reencuadre tras montar/redimensionar el contenedor.
    setTimeout(() => this.map?.invalidateSize(), 250);
    setTimeout(() => this.map?.invalidateSize(), 700);
  }

  private renderCircles(L: typeof import('leaflet')): void {
    this.circles.forEach(c => this.map?.removeLayer(c));
    this.circles = [];

    const filtered = this.filteredZones;

    filtered.forEach(zone => {
      const radius = this.radiusFor(zone);
      const circle = L.circleMarker([zone.latitude, zone.longitude], {
        radius,
        color: '#ffffff',
        weight: 2,
        fillColor: zone.color,
        fillOpacity: 0.9,
      });

      circle.bindPopup(this.buildPopupHtml(zone), { closeButton: true });
      circle.on('click', () => {
        this.selectedZone = zone;
      });

      circle.addTo(this.map!);
      this.circles.push(circle);
    });

    this.fitBounds();
  }

  private buildPopupHtml(zone: PopulationZone): string {
    const raizalPct = zone.total_population > 0
      ? ((zone.raizal_population / zone.total_population) * 100).toFixed(1)
      : '0';
    return `
      <div class="zone-popup" style="font-family: Roboto, sans-serif; min-width: 180px;">
        <strong style="font-size: 13px; display:block; margin-bottom:4px;">${zone.name}</strong>
        <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
          <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:${zone.color};"></span>
          <span style="font-size:11px; color:#5c6b7a;">${zone.zone_type}</span>
        </div>
        <div style="font-size:12px; line-height:1.6;">
          <div><b>Población total:</b> ${zone.total_population.toLocaleString('es-CO')}</div>
          <div><b>Raizales:</b> ${zone.raizal_population.toLocaleString('es-CO')} <span style="color:#5c6b7a;">(${raizalPct}%)</span></div>
          <div><b>Otros residentes:</b> ${zone.other_population.toLocaleString('es-CO')}</div>
        </div>
        <p style="font-size:11px; color:#5c6b7a; margin:6px 0 0;">${zone.description || ''}</p>
      </div>
    `;
  }

  get filteredZones(): PopulationZone[] {
    if (!this.zones.length) return [];
    if (this.filter === 'todos') return this.zones;
    if (this.filter === 'raizal_dominante') {
      return this.zones.filter(z =>
        z.total_population > 0 && z.raizal_population / z.total_population >= 0.5
      );
    }
    if (this.filter === 'mayor_poblacion') {
      const max = Math.max(...this.zones.map(z => z.total_population));
      return this.zones.filter(z => z.total_population >= max * 0.8);
    }
    return this.zones;
  }

  onFilterChange(): void {
    if (this.map && this.leafletModule) {
      this.renderCircles(this.leafletModule);
    }
  }

  private radiusFor(zone: PopulationZone): number {
    const t = zone.total_population;
    if (t >= 6000) return 26;
    if (t >= 4000) return 21;
    if (t >= 2500) return 17;
    if (t >= 1200) return 13;
    return 9;
  }

  private fitBounds(): void {
    if (!this.map || !this.circles.length || !this.leafletModule) return;
    const L = this.leafletModule;
    try {
      const latLngs = this.circles.map(c => c.getLatLng()).filter(Boolean);
      if (!latLngs.length) return;
      const bounds = L.latLngBounds(latLngs);
      this.map.fitBounds(bounds, { padding: [40, 40] });
    } catch { /* noop */ }
  }

  selectZone(zone: PopulationZone): void {
    this.selectedZone = zone;
    if (this.map && this.leafletModule) {
      const L = this.leafletModule;
      const latlng: L.LatLngExpression = [zone.latitude, zone.longitude];
      this.map.flyTo(latlng, 14, { duration: 0.8 });
      if (this.destinyMarker) this.map.removeLayer(this.destinyMarker);
      this.destinyMarker = L.circleMarker(latlng, {
        radius: 8,
        color: '#0b5ed7',
        weight: 3,
        fillColor: '#ffffff',
        fillOpacity: 1,
      }).addTo(this.map);
    }
  }

  clearSelection(): void {
    this.selectedZone = null;
    if (this.destinyMarker && this.map) {
      this.map.removeLayer(this.destinyMarker);
      this.destinyMarker = null;
    }
    if (this.map) {
      this.map.flyTo([12.556, -81.7075], 12, { duration: 0.8 });
    }
  }

  get raizalDominantCount(): number {
    return this.zones.filter(z => z.total_population > 0 && z.raizal_population / z.total_population >= 0.5).length;
  }

  raizalPct(zone: PopulationZone): number {
    if (!zone || !zone.total_population) return 0;
    return Math.round((zone.raizal_population / zone.total_population) * 100);
  }

  async reload(): Promise<void> {
    await this.loadData();
  }

  private async loadLeaflet(): Promise<typeof import('leaflet')> {
    return import('leaflet');
  }

  ngOnDestroy(): void {
    this.map?.remove();
    this.map = null;
  }
}