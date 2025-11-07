import { Injectable } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model } from 'mongoose';
import { Listing } from './schemas/listing.schema';
import { CreateListingDto, UpdateListingDto } from './dto/listing.dto';

@Injectable()
export class ListingsService {
  constructor(
    @InjectModel(Listing.name) private listingModel: Model<Listing>,
  ) {}

  async create(
    createListingDto: CreateListingDto,
    userId: string,
  ): Promise<Listing> {
    const created = new this.listingModel({
      ...createListingDto,
      owner: userId,
    });
    return created.save();
  }

  async findAll(query: any = {}): Promise<Listing[]> {
    return this.listingModel.find(query).exec();
  }

  async findOne(id: string): Promise<Listing> {
    return this.listingModel.findById(id).exec();
  }

  async update(
    id: string,
    updateListingDto: UpdateListingDto,
  ): Promise<Listing> {
    return this.listingModel
      .findByIdAndUpdate(id, updateListingDto, { new: true })
      .exec();
  }

  async remove(id: string): Promise<Listing> {
    return this.listingModel.findByIdAndDelete(id).exec();
  }

  async findByOwner(userId: string): Promise<Listing[]> {
    return this.listingModel.find({ owner: userId }).exec();
  }
}
